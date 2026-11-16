<?php

declare(strict_types=1);

return [
    'attributes' => [
        'name' => 'name',
        'active' => 'active',
        'settlement_days' => 'settlement days',
        'fee_percent' => 'percentage fee',
        'fee_fixed' => 'fixed fee',
    ],

    'validation' => [
        'name.required' => 'The terminal name is required.',
        'settlement_days.required' => 'The settlement period in days is required.',
        'settlement_days.min' => 'The settlement period cannot be negative.',
        'fee_percent.required' => 'The percentage fee is required.',
        'fee_percent.min' => 'The percentage fee cannot be negative.',
        'fee_percent.max' => 'The percentage fee cannot exceed :max%.',
        'fee_fixed.integer' => 'The fixed fee must be an integer amount in cents.',
        'fee_fixed.min' => 'The fixed fee cannot be negative.',
        'fee_fixed.max' => 'The fixed fee cannot exceed :max cents.',
    ],
];
