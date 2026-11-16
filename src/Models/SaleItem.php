<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Models;

use Am2tec\Pdv\Enums\SaleItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Frozen snapshot of a catalog line. Amounts are integer cents.
 *
 * @property int $id
 * @property string $sale_id
 * @property SaleItemType $type
 * @property string $source_type
 * @property string $source_id
 * @property string $description
 * @property int $qty
 * @property int $unit_amount
 * @property int $total_amount
 * @property array<string, mixed>|null $metadata
 */
class SaleItem extends Model
{
    protected $table = 'pdv_sale_items';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'source_type',
        'source_id',
        'description',
        'qty',
        'unit_amount',
        'total_amount',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SaleItemType::class,
            'qty' => 'integer',
            'unit_amount' => 'integer',
            'total_amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
