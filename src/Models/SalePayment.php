<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Models;

use Am2tec\Pdv\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Payment line. Card fee/settlement fields are frozen from the terminal at sale time.
 *
 * @property int $id
 * @property string $sale_id
 * @property PaymentMethod $method
 * @property int $amount
 * @property int|null $terminal_id
 * @property string|null $fee_percent
 * @property int $fee_amount
 * @property int $fee_fixed_snapshot
 * @property int|null $settlement_days
 * @property Carbon|null $expected_settlement_at
 */
class SalePayment extends Model
{
    protected $table = 'pdv_sale_payments';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'method',
        'amount',
        'terminal_id',
        'fee_percent',
        'fee_amount',
        'fee_fixed_snapshot',
        'settlement_days',
        'expected_settlement_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'terminal_id' => 'integer',
            'fee_percent' => 'decimal:2',
            'fee_amount' => 'integer',
            'fee_fixed_snapshot' => 'integer',
            'settlement_days' => 'integer',
            'expected_settlement_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id')->withTrashed();
    }
}
