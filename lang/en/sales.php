<?php

declare(strict_types=1);

return [
    'attributes' => [
        'items.*.type' => 'item type',
        'items.*.qty' => 'quantity',
        'items.*.unit_amount' => 'unit amount',
        'payments.*.method' => 'payment method',
        'payments.*.amount' => 'payment amount',
        'payments.*.terminal_id' => 'terminal',
        'discount' => 'discount',
        'discount.mode' => 'discount type',
        'discount.amount' => 'discount amount',
        'discount.percent_bps' => 'discount percent',
        'discount.code' => 'coupon',
    ],

    'validation' => [
        'items.required' => 'At least one item is required.',
        'items.min' => 'At least one item is required.',
        'items.*.type.enum' => 'The item type must be product, service or subscription.',
        'items.*.qty.min' => 'The quantity must be at least 1.',
        'items.*.unit_amount.min' => 'The unit amount cannot be negative.',
        'payments.required' => 'At least one payment is required.',
        'payments.min' => 'At least one payment is required.',
        'payments.*.method.enum' => 'The payment method must be cash, PIX or card.',
        'payments.*.amount.min' => 'The payment amount must be at least 1 cent.',
    ],

    'errors' => [
        'terminal_required' => 'Card payments require a terminal.',
        'terminal_unavailable' => 'The given terminal does not exist or is inactive.',
        'payments_mismatch' => 'The payments must add up to the sale total.',
        'fulfillment_rejected' => 'The sale could not be completed: one of the items was rejected.',
        'fulfillment_failed' => 'The sale could not be completed: failed to process the items. Nothing was saved.',
        'cancel_not_completed' => 'Only completed sales can be cancelled.',
        'cancel_not_today' => 'Only sales made today can be cancelled.',
        'cancel_card_settled' => 'Cannot cancel: a card payment has already settled.',
        'discount_coupon_required' => 'A valid coupon is required to apply this discount.',
        'discount_invalid_mode' => 'The given discount type is invalid.',
    ],
];
