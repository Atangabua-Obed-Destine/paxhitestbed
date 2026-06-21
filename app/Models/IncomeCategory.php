<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class IncomeCategory extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'slug', 'description', 'status',
    ];

    public function incomes()
    {
        return $this->hasMany(Income::class, 'category_id', 'id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Income Category {$event}: {$title}, Status: {$status}";
    }
}
