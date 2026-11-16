<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Services;

use Am2tec\Pdv\Data\SaleCompletedPayload;
use Am2tec\Pdv\Enums\PaymentMethod;
use Am2tec\Pdv\Enums\SaleStatus;
use Am2tec\Pdv\Events\SaleCancelled;
use Am2tec\Pdv\Models\Sale;
use Am2tec\Pdv\Models\SalePayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleCancellationService
{
    /**
     * Cancels a completed sale. Allowed only when ALL hold, checked against the persisted row
     * (locked for update, so concurrent cancels cannot both succeed):
     *  - status is completed;
     *  - `occurred_at` civil date is today in the app timezone;
     *  - no card payment has `expected_settlement_at` before the start of today.
     *
     * `SaleCancelled` is dispatched only after the outermost transaction commits.
     *
     * @throws ValidationException when the sale cannot be cancelled
     */
    public function cancel(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale): Sale {
            $current = Sale::query()
                ->with(['items', 'payments'])
                ->lockForUpdate()
                ->findOrFail($sale->getKey());

            $this->assertCancellable($current);

            $current->status = SaleStatus::Cancelled;
            $current->save();

            DB::afterCommit(static function () use ($current): void {
                event(new SaleCancelled(SaleCompletedPayload::fromSale($current)));
            });

            return $current;
        });
    }

    /**
     * @throws ValidationException
     */
    private function assertCancellable(Sale $sale): void
    {
        $timezone = (string) config('app.timezone');
        $now = Carbon::now($timezone);

        if ($sale->status !== SaleStatus::Completed) {
            $this->refuse(__('pdv::sales.errors.cancel_not_completed'));
        }

        if ($sale->occurred_at->copy()->setTimezone($timezone)->toDateString() !== $now->toDateString()) {
            $this->refuse(__('pdv::sales.errors.cancel_not_today'));
        }

        $startOfToday = $now->copy()->startOfDay();

        $settled = $sale->payments->contains(
            static fn (SalePayment $payment): bool => $payment->method === PaymentMethod::Card
                && $payment->expected_settlement_at !== null
                && $payment->expected_settlement_at->lt($startOfToday),
        );

        if ($settled) {
            $this->refuse(__('pdv::sales.errors.cancel_card_settled'));
        }
    }

    /**
     * @throws ValidationException
     */
    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['sale' => [$message]]);
    }
}
