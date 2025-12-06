<?php

namespace App\Enums;

enum SelfHostedServiceType: string
{
    case Mempool = 'mempool';
    case LNbits = 'lnbits';
    case Alby = 'alby';
    case Other = 'other';
}
