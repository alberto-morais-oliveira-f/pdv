<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Enums;

enum SaleStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
