<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Models;

use Am2tec\Pdv\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Counter sale. All amounts are integer cents.
 *
 * @property string $id
 * @property SaleStatus $status
 * @property int|null $operator_id
 * @property string|null $customer_type
 * @property string|null $customer_id
 * @property int $gross_amount
 * @property int $fees_amount
 * @property int $net_amount
 * @property Carbon $occurred_at
 * @property-read Collection<int, SaleItem> $items
 * @property-read Collection<int, SalePayment> $payments
 */
class Sale extends Model
{
    use HasUuids;

    protected $table = 'pdv_sales';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'status',
        'operator_id',
        'customer_type',
        'customer_id',
        'gross_amount',
        'fees_amount',
        'net_amount',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'operator_id' => 'integer',
            'gross_amount' => 'integer',
            'fees_amount' => 'integer',
            'net_amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id')->orderBy('id');
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sale_id')->orderBy('id');
    }
}
