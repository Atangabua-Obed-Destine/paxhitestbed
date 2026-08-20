<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-curated question/answer pairs surfaced to the chat, chiefly so the
 * public (unauthenticated) widget has something useful to say without any
 * access to personal records.
 */
class ChatKnowledgeEntry extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'question',
        'answer',
        'tags',
        'for_web',
        'for_application',
        'for_student',
        'for_admin',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'for_web' => 'boolean',
        'for_application' => 'boolean',
        'for_student' => 'boolean',
        'for_admin' => 'boolean',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Active entries visible to a given surface. */
    public function scopeForSurface($query, string $surface)
    {
        $column = 'for_' . $surface;

        return $query->where('status', 1)->where($column, 1);
    }
}
