<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Card machine / payment terminal. `fee_fixed` is stored in cents.
 *
 * @property int $id
 * @property string $name
 * @property bool $active
 * @property int $settlement_days
 * @property string $fee_percent
 * @property int $fee_fixed
 */
class Terminal extends Model
{
    use SoftDeletes;

    protected $table = 'pdv_terminals';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'active',
        'settlement_days',
        'fee_percent',
        'fee_fixed',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
        'fee_fixed' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'settlement_days' => 'integer',
            'fee_percent' => 'decimal:2',
            'fee_fixed' => 'integer',
        ];
    }
}
