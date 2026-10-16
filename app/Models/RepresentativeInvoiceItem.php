<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepresentativeInvoiceItem extends Model
{
    protected $fillable = [
        'representative_invoice_id',
        'store_id',
        'name',
        'quantity',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(RepresentativeInvoice::class, 'representative_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
