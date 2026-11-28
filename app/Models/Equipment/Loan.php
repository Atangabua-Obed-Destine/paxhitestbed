<?php

namespace App\Models\Equipment;

use App\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One time an item went out. The borrower is a [type, id] pair, as in the
 * mailbox: 'staff' = users.id, 'student' = students.id. So is whoever
 * brought it back, who need not be the borrower.
 */
class Loan extends Model
{
    public const OUT = 'out';
    public const RETURNED = 'returned';
    public const LOST = 'lost';

    public const FINE = 'fine';
    public const DAMAGED = 'damaged';
    public const MISSING = 'missing';

    public const PURPOSES = ['self', 'class', 'lecturer'];

    protected $table = 'equipment_loans';

    protected $fillable = [
        'equipment_item_id', 'borrower_type', 'borrower_id', 'purpose', 'purpose_detail', 'lecturer_id',
        'desk_id', 'lent_at', 'due_at', 'lent_by', 'accessories_out', 'returned_at', 'returned_by_type',
        'returned_by_id', 'received_by', 'return_condition', 'accessories_back', 'return_note', 'status',
        'follow_up', 'follow_up_cleared_at', 'follow_up_cleared_by', 'follow_up_note', 'mailbox_thread_id',
        'reminders_sent', 'last_reminded_at',
    ];

    protected $casts = [
        'lent_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
        'follow_up_cleared_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'accessories_out' => 'array',
        'accessories_back' => 'array',
        'follow_up' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'equipment_item_id')->withTrashed();
    }

    public function desk()
    {
        return $this->belongsTo(Desk::class, 'desk_id');
    }

    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function lender()
    {
        return $this->belongsTo(User::class, 'lent_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function borrower(): array
    {
        return [$this->borrower_type, (int) $this->borrower_id];
    }

    public function returner(): ?array
    {
        return $this->returned_by_type ? [$this->returned_by_type, (int) $this->returned_by_id] : null;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::OUT && $this->due_at->isPast();
    }

    /** Missing accessories: what went out and did not come back. */
    public function missingAccessories(): array
    {
        return array_values(array_diff((array) $this->accessories_out, (array) ($this->accessories_back ?? [])));
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::OUT);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::OUT)->where('due_at', '<', now());
    }

    public function scopeOf($query, array $member)
    {
        return $query->where('borrower_type', $member[0])->where('borrower_id', $member[1]);
    }
}
