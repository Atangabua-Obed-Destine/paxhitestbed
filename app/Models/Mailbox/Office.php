<?php

namespace App\Models\Mailbox;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $table = 'mailbox_offices';

    protected $fillable = ['title', 'description', 'status'];

    public function members()
    {
        return $this->belongsToMany(User::class, 'mailbox_office_members', 'office_id', 'user_id')->withTimestamps();
    }
}
