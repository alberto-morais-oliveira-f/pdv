<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Enums;

enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
