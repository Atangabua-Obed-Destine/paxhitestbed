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
        'profit_centre',
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
        $lines = static::active()
            ->orderByRaw("FIELD(section, 'income', 'expenditure', 'capital')")
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return static::inTreeOrder($lines);
    }

    /**
     * Flatten lines so each heading is followed immediately by the lines filed
     * under it.
     *
     * sort_order is one run of numbers across a section, so on its own it does
     * not guarantee a group is contiguous — a line renumbered by hand could sit
     * between two other headings' children. The sheet subtotals each group at
     * its foot, which is only meaningful if the group is unbroken, so the
     * heading structure decides the order and the numbers order within it.
     *
     * A line whose heading is retired or missing is kept, at the top level of
     * its section: dropping it would quietly remove money from the sheet.
     *
     * @param  \Illuminate\Support\Collection<int, self> $lines
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function inTreeOrder($lines): \Illuminate\Support\Collection
    {
        $ids = $lines->pluck('id')->all();

        $childrenOf = $lines
            ->filter(fn (self $line) => $line->parent_id && in_array($line->parent_id, $ids, true))
            ->sortBy([['sort_order', 'asc'], ['code', 'asc']])
            ->groupBy('parent_id');

        $ordered = collect();

        foreach (['income', 'expenditure', 'capital'] as $section) {
            $topLevel = $lines
                ->where('section', $section)
                ->filter(fn (self $line) => !$line->parent_id || !in_array($line->parent_id, $ids, true))
                ->sortBy([['sort_order', 'asc'], ['code', 'asc']]);

            foreach ($topLevel as $line) {
                $ordered->push($line);

                foreach ($childrenOf->get($line->id, collect()) as $child) {
                    $ordered->push($child);
                }
            }
        }

        return $ordered->values();
    }

    /**
     * Where each heading's group ends, as [last child line id => heading].
     *
     * The three renderings of the sheet — screen, PDF and workbook — all need
     * to know when to draw a subtotal, and all three walk the same flat list.
     * Deciding it once here keeps them from disagreeing about where a group
     * stops. A heading with no lines under it yields nothing: a subtotal of one
     * empty group directly beneath its own zero would be noise.
     *
     * @param  \Illuminate\Support\Collection<int, self> $orderedLines
     * @return array<int, self>
     */
    public static function groupEnds($orderedLines): array
    {
        $ends = [];
        $currentHeader = null;
        $lastChildId = null;

        foreach ($orderedLines as $line) {
            if ($line->is_header) {
                if ($currentHeader && $lastChildId) {
                    $ends[$lastChildId] = $currentHeader;
                }
                $currentHeader = $line;
                $lastChildId = null;
                continue;
            }

            if ($currentHeader && $line->parent_id === $currentHeader->id) {
                $lastChildId = $line->id;
                continue;
            }

            // A line standing outside any heading closes the group before it.
            if ($currentHeader && $lastChildId) {
                $ends[$lastChildId] = $currentHeader;
            }
            $currentHeader = null;
            $lastChildId = null;
        }

        if ($currentHeader && $lastChildId) {
            $ends[$lastChildId] = $currentHeader;
        }

        return $ends;
    }

    public function getLabelAttribute(): string
    {
        return $this->code . ' — ' . $this->name;
    }
}
