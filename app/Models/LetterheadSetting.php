<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * The institution's letterhead, configured once and reused by every document.
 */
class LetterheadSetting extends Model
{
    use Auditable;

    public const MODE_HTML = 'html';
    public const MODE_RESERVE = 'reserve_space';
    public const MODE_NONE = 'none';

    protected $fillable = [
        'mode', 'html', 'reserve_height_mm', 'status', 'updated_by',
    ];

    protected $casts = [
        'reserve_height_mm' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * The one settings row, created on demand so no screen ever fails on a
     * fresh install.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::create(['mode' => self::MODE_NONE]);
    }

    /** Is there anything to render at the top of a document? */
    public function isActive(): bool
    {
        return $this->status && $this->mode !== self::MODE_NONE;
    }
}
