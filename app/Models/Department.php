<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Traits\Auditable;

class Department extends Model
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

    public function users()
    {
        return $this->hasMany(User::class, 'department_id', 'id');
    }

    public function visitors()
    {
        return $this->hasMany(Visitor::class, 'department_id', 'id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Department {$event}: {$title}, Status: {$status}";
    }
}
