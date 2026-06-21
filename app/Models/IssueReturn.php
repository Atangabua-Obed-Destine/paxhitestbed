<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\User;

class IssueReturn extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'member_id', 'book_id', 'issue_date', 'due_date', 'return_date', 'penalty', 'status', 'issued_by', 'received_by',
    ];

    public function book()
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function member()
    {
        return $this->belongsTo(LibraryMember::class, 'member_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
