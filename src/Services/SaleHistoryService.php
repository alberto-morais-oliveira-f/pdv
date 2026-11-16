<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Services;

use Am2tec\Pdv\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class SaleHistoryService
{
    /**
     * Sales (any status) whose `occurred_at` falls on the given civil day in the app timezone, newest first.
     *
     * @return LengthAwarePaginator<int, Sale>
     */
    public function forDay(Carbon $day, int $perPage = 50): LengthAwarePaginator
    {
        $start = $day->copy()->setTimezone((string) config('app.timezone'))->startOfDay();

        return Sale::query()
            ->with(['items', 'payments'])
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $start->copy()->addDay())
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Recent sales for a customer across days, newest first.
     *
     * @return LengthAwarePaginator<int, Sale>
     */
    public function forCustomer(string $customerType, string $customerId, int $limit = 5): LengthAwarePaginator
    {
        return Sale::query()
            ->with(['items', 'payments'])
            ->where('customer_type', $customerType)
            ->where('customer_id', $customerId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($limit)
            ->withQueryString();
    }
}
