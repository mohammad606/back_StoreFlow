<?php

namespace App\Enums;

enum RepresentativeInvoiceStatusEnum: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
