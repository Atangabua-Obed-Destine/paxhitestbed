<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\User;

class MeetingSchedule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type_id', 'user_id', 'name', 'father_name', 'phone', 'email', 'address', 'purpose', 'id_no', 'token', 'date', 'in_time', 'out_time', 'persons', 'note', 'attach', 'status', 'created_by', 'updated_by',
    ];

    public function type()
    {
        return $this->belongsTo(MeetingType::class, 'type_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
