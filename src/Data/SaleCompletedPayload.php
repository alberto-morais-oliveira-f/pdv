<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Data;

use Am2tec\Pdv\Enums\PaymentMethod;
use Am2tec\Pdv\Models\Sale;
use Am2tec\Pdv\Models\SaleItem;
use Am2tec\Pdv\Models\SalePayment;

/**
 * Stable `pdv.sale.completed` contract. Monetary values are integer cents.
 */
final readonly class SaleCompletedPayload
{
    /**
     * @param  array{type: string, id: string}|null  $customer
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $payments
     * @param  array{gross: int, discount: int, fees: int, net: int}  $totals
     * @param  array{type: string, percent_bps: int|null, coupon_id: int|null, coupon_code: string|null}  $discount
     */
    public function __construct(
        public string $saleId,
        public string $occurredAt,
        public ?int $operatorId,
        public ?array $customer,
        public array $items,
        public array $payments,
        public array $totals,
        public array $discount,
    ) {}

    public static function fromSale(Sale $sale): self
    {
        $sale->loadMissing(['items', 'payments']);

        return new self(
            saleId: $sale->id,
            occurredAt: $sale->occurred_at->toIso8601String(),
            operatorId: $sale->operator_id,
            customer: $sale->customer_type !== null && $sale->customer_id !== null
                ? ['type' => $sale->customer_type, 'id' => $sale->customer_id]
                : null,
            items: $sale->items->map(fn (SaleItem $item): array => [
                'type' => $item->type->value,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
                'description' => $item->description,
                'qty' => $item->qty,
                'unit_amount' => $item->unit_amount,
                'total_amount' => $item->total_amount,
                'metadata' => $item->metadata ?? [],
            ])->all(),
            payments: $sale->payments->map(fn (SalePayment $payment): array => self::paymentToArray($payment))->all(),
            totals: [
                'gross' => $sale->gross_amount,
                'discount' => $sale->discount_amount,
                'fees' => $sale->fees_amount,
                'net' => $sale->net_amount,
            ],
            discount: [
                'type' => $sale->discount_type->value,
                'percent_bps' => $sale->discount_percent_bps,
                'coupon_id' => $sale->coupon_id,
                'coupon_code' => $sale->coupon_code_snapshot,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sale_id' => $this->saleId,
            'occurred_at' => $this->occurredAt,
            'operator_id' => $this->operatorId,
            'customer' => $this->customer,
            'items' => $this->items,
            'payments' => $this->payments,
            'totals' => $this->totals,
            'discount' => $this->discount,
        ];
    }

    /**
     * Fee/settlement fields are only meaningful for card payments.
     *
     * @return array<string, mixed>
     */
    private static function paymentToArray(SalePayment $payment): array
    {
        $isCard = $payment->method === PaymentMethod::Card;

        return [
            'method' => $payment->method->value,
            'amount' => $payment->amount,
            'terminal_id' => $payment->terminal_id,
            'fee_percent' => $isCard && $payment->fee_percent !== null ? (float) $payment->fee_percent : null,
            'fee_amount' => $isCard ? $payment->fee_amount : null,
            'settlement_days' => $isCard ? $payment->settlement_days : null,
            'expected_settlement_at' => $isCard ? $payment->expected_settlement_at?->toIso8601String() : null,
        ];
    }
}
