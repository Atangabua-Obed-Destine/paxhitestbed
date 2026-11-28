<?php

namespace App\Models\Equipment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One physical piece of lendable equipment, known by the code on its sticker.
 *
 * Whether it is out is not stored here: it is out when it has an open loan.
 * `state` only says whether it can be lent at all.
 */
class Item extends Model
{
    use SoftDeletes;

    public const ACTIVE = 'active';
    public const MAINTENANCE = 'maintenance';
    public const LOST = 'lost';
    public const RETIRED = 'retired';

    public const STATES = [self::ACTIVE, self::MAINTENANCE, self::LOST, self::RETIRED];

    protected $table = 'equipment_items';

    protected $fillable = [
        'code', 'name', 'category', 'accessories', 'students_may_borrow', 'desk_id',
        'fixed_asset_id', 'notes', 'state', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'accessories' => 'array',
        'students_may_borrow' => 'boolean',
    ];

    public static function stateLabels(): array
    {
        return [
            self::ACTIVE => __('Can be lent'),
            self::MAINTENANCE => __('Being repaired'),
            self::LOST => __('Lost'),
            self::RETIRED => __('No longer lent'),
        ];
    }

    /** Sticker codes: upper case, and only what a Code 39 barcode can carry. */
    public static function normaliseCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    public function desk()
    {
        return $this->belongsTo(Desk::class, 'desk_id');
    }

    public function fixedAsset()
    {
        return $this->belongsTo(\App\Models\FixedAsset::class, 'fixed_asset_id');
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'equipment_item_id');
    }

    public function openLoan()
    {
        return $this->hasOne(Loan::class, 'equipment_item_id')->where('status', Loan::OUT);
    }

    public function accessoryList(): array
    {
        return array_values(array_filter(array_map('trim', (array) ($this->accessories ?? [])), fn ($a) => $a !== ''));
    }

    public function stateLabel(): string
    {
        return self::stateLabels()[$this->state] ?? $this->state;
    }
}
