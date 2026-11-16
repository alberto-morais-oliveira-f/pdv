<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Tests\Support;

use Am2tec\Pdv\Enums\PaymentMethod;
use Am2tec\Pdv\Enums\SaleItemType;
use Am2tec\Pdv\Enums\SaleStatus;
use Am2tec\Pdv\Models\Sale;
use Am2tec\Pdv\Models\Terminal;
use DateTimeInterface;

final class SaleBuilder
{
    /**
     * Persists a sale with one 1000-cent product item and one payment.
     *
     * @param  array<string, mixed>|null  $card  null for a cash sale; otherwise overrides for the card payment
     */
    public static function make(
        ?DateTimeInterface $occurredAt = null,
        SaleStatus $status = SaleStatus::Completed,
        ?array $card = null,
    ): Sale {
        $sale = Sale::query()->create([
            'status' => $status,
            'operator_id' => 1,
            'gross_amount' => 1000,
            'fees_amount' => 0,
            'net_amount' => 1000,
            'occurred_at' => $occurredAt ?? now(),
        ]);

        $sale->items()->create([
            'type' => SaleItemType::Product,
            'source_type' => 'stock_product',
            'source_id' => '10',
            'description' => 'Kimono A2',
            'qty' => 1,
            'unit_amount' => 1000,
            'total_amount' => 1000,
            'metadata' => [],
        ]);

        if ($card === null) {
            $sale->payments()->create(['method' => PaymentMethod::Cash, 'amount' => 1000]);
        } else {
            $terminal = Terminal::query()->create([
                'name' => 'Maquininha',
                'settlement_days' => 30,
                'fee_percent' => 2.99,
                'fee_fixed' => 10,
            ]);

            $sale->payments()->create(array_merge([
                'method' => PaymentMethod::Card,
                'amount' => 1000,
                'terminal_id' => $terminal->id,
                'fee_percent' => 2.99,
                'fee_amount' => 40,
                'fee_fixed_snapshot' => 10,
                'settlement_days' => 30,
                'expected_settlement_at' => now()->addDays(30)->startOfDay(),
            ], $card));
        }

        return $sale->load(['items', 'payments']);
    }
}
