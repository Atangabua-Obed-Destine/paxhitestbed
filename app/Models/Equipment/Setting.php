<?php

namespace App\Models\Equipment;

use Illuminate\Database\Eloquent\Model;

/** The one row of desk settings, shared by the equipment and library desks. */
class Setting extends Model
{
    protected $table = 'loan_desk_settings';

    protected $fillable = ['default_due_time', 'first_reminder_minutes', 'daily_reminder_time', 'library_loan_days', 'block_across_desks'];

    protected $casts = ['block_across_desks' => 'boolean'];

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([])->fresh();
    }
}
