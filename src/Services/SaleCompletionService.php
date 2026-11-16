<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Services;

use Am2tec\Pdv\Contracts\ProductFulfiller;
use Am2tec\Pdv\Contracts\ServiceFulfiller;
use Am2tec\Pdv\Contracts\SubscriptionFulfiller;
use Am2tec\Pdv\Data\SaleCompletedPayload;
use Am2tec\Pdv\Enums\PaymentMethod;
use Am2tec\Pdv\Enums\SaleItemType;
use Am2tec\Pdv\Enums\SaleStatus;
use Am2tec\Pdv\Events\SaleCompleted;
use Am2tec\Pdv\Exceptions\FulfillmentRejected;
use Am2tec\Pdv\Exceptions\SaleFulfillmentFailed;
use Am2tec\Pdv\Models\Sale;
use Am2tec\Pdv\Models\Terminal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaleCompletionService
{
    public function __construct(
        private readonly ProductFulfiller $products,
        private readonly ServiceFulfiller $services,
        private readonly SubscriptionFulfiller $subscriptions,
    ) {}

    /**
     * @param  array{
     *     operator_id?: int|null,
     *     customer_type?: string|null,
     *     customer_id?: string|int|null,
     *     occurred_at?: \DateTimeInterface|string|null,
     *     items: array<int, array<string, mixed>>,
     *     payments: array<int, array<string, mixed>>
     * }  $data
     *
     * Not idempotent: each call creates a new sale. `SaleCompleted` is dispatched only after
     * the outermost transaction commits.
     *
     * @throws ValidationException when payments do not match the gross, a card has no usable terminal or a fulfiller threw FulfillmentRejected
     * @throws SaleFulfillmentFailed when a host fulfiller throws (everything is rolled back)
     */
    public function complete(array $data): Sale
    {
        $occurredAt = isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : Carbon::now();

        $items = $this->buildItems($data['items']);
        $gross = array_sum(array_column($items, 'total_amount'));
        $payments = $this->buildPayments($data['payments'], $gross, $occurredAt);
        $fees = array_sum(array_column($payments, 'fee_amount'));

        $sale = DB::transaction(function () use ($data, $items, $payments, $gross, $fees, $occurredAt): Sale {
            $sale = Sale::query()->create([
                'status' => SaleStatus::Completed,
                'operator_id' => $data['operator_id'] ?? null,
                'customer_type' => $data['customer_type'] ?? null,
                'customer_id' => isset($data['customer_id']) ? (string) $data['customer_id'] : null,
                'gross_amount' => $gross,
                'fees_amount' => $fees,
                'net_amount' => $gross - $fees,
                'occurred_at' => $occurredAt,
            ]);

            $sale->items()->createMany($items);
            $sale->payments()->createMany($payments);
            $sale->load(['items', 'payments']);

            $this->fulfill($sale);

            DB::afterCommit(static function () use ($sale): void {
                event(new SaleCompleted(SaleCompletedPayload::fromSale($sale)));
            });

            return $sale;
        });

        return $sale;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $rawItems): array
    {
        return array_map(function (array $item): array {
            $qty = (int) $item['qty'];
            $unit = (int) $item['unit_amount'];

            return [
                'type' => SaleItemType::from((string) $item['type']),
                'source_type' => (string) $item['source_type'],
                'source_id' => (string) $item['source_id'],
                'description' => (string) $item['description'],
                'qty' => $qty,
                'unit_amount' => $unit,
                'total_amount' => $qty * $unit,
                'metadata' => $item['metadata'] ?? [],
            ];
        }, array_values($rawItems));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawPayments
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function buildPayments(array $rawPayments, int $gross, Carbon $occurredAt): array
    {
        $errors = [];
        $payments = [];
        $paid = 0;

        foreach (array_values($rawPayments) as $index => $raw) {
            $method = PaymentMethod::from((string) $raw['method']);
            $amount = (int) $raw['amount'];
            $paid += $amount;

            $payment = [
                'method' => $method,
                'amount' => $amount,
                'terminal_id' => null,
                'fee_percent' => null,
                'fee_amount' => 0,
                'fee_fixed_snapshot' => 0,
                'settlement_days' => null,
                'expected_settlement_at' => null,
            ];

            if ($method === PaymentMethod::Card) {
                $terminalId = $raw['terminal_id'] ?? null;

                if ($terminalId === null || $terminalId === '') {
                    $errors["payments.{$index}.terminal_id"] = [__('pdv::sales.errors.terminal_required')];
                } else {
                    $terminal = Terminal::query()->where('active', true)->find($terminalId);

                    if ($terminal === null) {
                        $errors["payments.{$index}.terminal_id"] = [__('pdv::sales.errors.terminal_unavailable')];
                    } else {
                        $payment = $this->freezeCardTerms($payment, $terminal, $occurredAt);
                    }
                }
            }

            $payments[] = $payment;
        }

        if ($paid !== $gross) {
            $errors['payments'] = [__('pdv::sales.errors.payments_mismatch')];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $payments;
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    private function freezeCardTerms(array $payment, Terminal $terminal, Carbon $occurredAt): array
    {
        $feePercent = (float) $terminal->fee_percent;

        $payment['terminal_id'] = $terminal->id;
        $payment['fee_percent'] = $terminal->fee_percent;
        $payment['fee_fixed_snapshot'] = $terminal->fee_fixed;
        $basisPoints = (int) round($feePercent * 100);
        $payment['fee_amount'] = intdiv($payment['amount'] * $basisPoints + 5000, 10000) + $terminal->fee_fixed;
        $payment['settlement_days'] = $terminal->settlement_days;
        $payment['expected_settlement_at'] = $occurredAt->copy()
            ->setTimezone((string) config('app.timezone'))
            ->addDays($terminal->settlement_days)
            ->startOfDay();

        return $payment;
    }

    /**
     * Runs host fulfillers in a fixed order: Product, Service, Subscription.
     * Only fulfillers whose item type is present in the sale are called.
     *
     * @throws SaleFulfillmentFailed
     */
    private function fulfill(Sale $sale): void
    {
        $steps = [
            [SaleItemType::Product, $this->products],
            [SaleItemType::Service, $this->services],
            [SaleItemType::Subscription, $this->subscriptions],
        ];

        foreach ($steps as [$type, $fulfiller]) {
            if (! $sale->items->contains(fn ($item): bool => $item->type === $type)) {
                continue;
            }

            try {
                $fulfiller->fulfill($sale);
            } catch (FulfillmentRejected $exception) {
                throw ValidationException::withMessages(['items' => [$exception->getMessage()]]);
            } catch (Throwable $exception) {
                report($exception);

                throw SaleFulfillmentFailed::because($exception);
            }
        }
    }
}
