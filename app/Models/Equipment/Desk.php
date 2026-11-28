<?php

namespace App\Models\Equipment;

use Illuminate\Database\Eloquent\Model;

/** An office that lends equipment: the ICT office, Stores. */
class Desk extends Model
{
    protected $table = 'equipment_desks';

    protected $fillable = ['name', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function items()
    {
        return $this->hasMany(Item::class, 'desk_id');
    }

    public static function active()
    {
        return static::where('status', true)->orderBy('name')->get();
    }
}
