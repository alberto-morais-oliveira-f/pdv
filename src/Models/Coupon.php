<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Models;

use Am2tec\Pdv\Enums\CouponType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Counter coupon. `value` is basis points when percent, cents when fixed.
 *
 * @property int $id
 * @property string $code
 * @property string|null $label
 * @property CouponType $type
 * @property int $value
 * @property bool $active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $max_uses
 * @property int $uses_count
 */
class Coupon extends Model
{
    protected $table = 'pdv_coupons';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'label',
        'type',
        'value',
        'active',
        'starts_at',
        'ends_at',
        'max_uses',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
        'uses_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => strtoupper(trim($value)),
        );
    }
}
