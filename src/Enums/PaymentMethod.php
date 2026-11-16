<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Pix = 'pix';
    case Card = 'card';
}
