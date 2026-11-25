<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemSupplier extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'email', 'phone', 'address', 'contact_person', 'designation', 'contact_person_email', 'contact_person_phone', 'description', 'status',
        'tax_number', 'payment_terms_days', 'bank_details',
    ];

    public function stocks()
    {
        return $this->hasMany(ItemStock::class, 'supplier_id', 'id');
    }

    public function bills()
    {
        return $this->hasMany(SupplierBill::class, 'supplier_id');
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class, 'supplier_id');
    }
}
