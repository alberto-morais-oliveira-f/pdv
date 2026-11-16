<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Enums;

enum DiscountType: string
{
    case None = 'none';
    case ManualFixed = 'manual_fixed';
    case ManualPercent = 'manual_percent';
    case Coupon = 'coupon';
}
