<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Enums;

enum SaleItemType: string
{
    case Product = 'product';
    case Service = 'service';
    case Subscription = 'subscription';
}
