<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of the Income & Expenditure sheet — e.g. "401 Travelling".
 *
 * A budget line is a management reporting dimension, not a ledger account. The
 * statutory chart is organised by nature of expense; the sheet is organised by
 * activity and is finer grained — account 605 "Eau et electricite" alone covers
 * six lines here. The two are joined through default_account_mappings, where a
 * category resolves to both a chart account and a budget line.
 */
class BudgetLine extends Model
{
    use Auditable;

    public const SECTION_INCOME = 'income';
    public const SECTION_EXPENDITURE = 'expenditure';
    public const SECTION_CAPITAL = 'capital';

    protected $fillable = [
        'code', 'name', 'name_fr', 'description', 'section', 'faculty_id',
        'is_header', 'parent_id', 'is_local', 'sort_order', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_header' => 'boolean',
        'is_local' => 'boolean',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeSection($query, string $section)
    {
        return $query->where('section', $section);
    }

    /** Only lines that can carry a figure — headers total their children. */
    public function scopePostable($query)
    {
        return $query->where('is_header', false);
    }

    /** The whole sheet in print order, headers followed by their children. */
    public static function sheet(): \Illuminate\Support\Collection
    {
        return static::active()
            ->orderByRaw("FIELD(section, 'income', 'expenditure', 'capital')")
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    public function getLabelAttribute(): string
    {
        return $this->code . ' — ' . $this->name;
    }
}
