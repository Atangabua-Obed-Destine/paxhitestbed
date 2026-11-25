<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** How much of one payment went to one bill. */
class SupplierPaymentAllocation extends Model
{
    protected $fillable = ['supplier_payment_id', 'supplier_bill_id', 'amount'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function bill()
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }
}
