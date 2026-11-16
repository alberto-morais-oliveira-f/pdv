<?php

declare(strict_types=1);

return [
    'attributes' => [
        'code' => 'code',
        'label' => 'label',
        'type' => 'type',
        'value' => 'value',
        'active' => 'active',
        'starts_at' => 'start date',
        'ends_at' => 'end date',
        'max_uses' => 'usage limit',
        'gross' => 'gross total',
    ],

    'validation' => [
        'code.required' => 'The coupon code is required.',
        'code.unique' => 'A coupon with this code already exists.',
        'type.required' => 'The coupon type is required.',
        'type.enum' => 'The coupon type must be percent or fixed.',
        'value.required' => 'The coupon value is required.',
        'value.min' => 'The coupon value must be at least :min.',
        'ends_at.after_or_equal' => 'The end date must be on or after the start date.',
        'max_uses.min' => 'The usage limit must be at least :min.',
        'gross.min' => 'The gross total cannot be negative.',
    ],

    'errors' => [
        'not_found' => 'Coupon not found.',
        'inactive' => 'This coupon is inactive.',
        'expired' => 'This coupon has expired.',
        'not_started' => 'This coupon is not valid yet.',
        'max_uses' => 'This coupon has reached its usage limit.',
    ],
];
