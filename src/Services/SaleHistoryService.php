<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Services;

use Am2tec\Pdv\Enums\PaymentMethod;
use Am2tec\Pdv\Enums\SaleItemType;
use Am2tec\Pdv\Enums\SaleStatus;
use Am2tec\Pdv\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SaleHistoryService
{
    /**
     * Newest-first listing. Date window, customer and item/payment filters combine.
     *
     * @param  array{
     *     from?: Carbon|null,
     *     to?: Carbon|null,
     *     status?: SaleStatus|null,
     *     customer_type?: string|null,
     *     customer_id?: string|null,
     *     operator_id?: int|null,
     *     payment_method?: PaymentMethod|null,
     *     terminal_id?: int|null,
     *     item_type?: SaleItemType|null,
     *     q?: string|null,
     *     category?: string|null,
     *     per_page?: int
     * }  $filters
     * @return LengthAwarePaginator<int, Sale>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $status = $filters['status'] ?? null;
        $customerType = $filters['customer_type'] ?? null;
        $customerId = $filters['customer_id'] ?? null;
        $operatorId = $filters['operator_id'] ?? null;
        $paymentMethod = $filters['payment_method'] ?? null;
        $terminalId = $filters['terminal_id'] ?? null;
        $itemType = $filters['item_type'] ?? null;
        $search = $filters['q'] ?? null;
        $category = $filters['category'] ?? null;
        $perPage = $filters['per_page'] ?? 50;

        return Sale::query()
            ->with(['items', 'payments'])
            ->when($from, fn (Builder $query): Builder => $query->where('occurred_at', '>=', $from))
            ->when($to, fn (Builder $query): Builder => $query->where('occurred_at', '<', $to))
            ->when($status, fn (Builder $query): Builder => $query->where('status', $status))
            ->when($customerType, fn (Builder $query): Builder => $query->where('customer_type', $customerType))
            ->when($customerId, fn (Builder $query): Builder => $query->where('customer_id', $customerId))
            ->when($operatorId, fn (Builder $query): Builder => $query->where('operator_id', $operatorId))
            ->when(
                $paymentMethod !== null || $terminalId !== null,
                function (Builder $query) use ($paymentMethod, $terminalId): Builder {
                    return $query->whereHas('payments', function (Builder $payments) use ($paymentMethod, $terminalId): void {
                        $payments
                            ->when($paymentMethod, fn (Builder $match): Builder => $match->where('method', $paymentMethod))
                            ->when($terminalId, fn (Builder $match): Builder => $match->where('terminal_id', $terminalId));
                    });
                },
            )
            ->when(
                $itemType !== null || $search !== null || $category !== null,
                function (Builder $query) use ($itemType, $search, $category): Builder {
                    return $query->whereHas('items', function (Builder $items) use ($itemType, $search, $category): void {
                        $items->when($itemType, fn (Builder $match): Builder => $match->where('type', $itemType));

                        if ($search !== null) {
                            $items->whereLike('description', '%'.addcslashes($search, '%_\\').'%', false);
                        }

                        $items->when($category, fn (Builder $match): Builder => $match->where('metadata->category', $category));
                    });
                },
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
