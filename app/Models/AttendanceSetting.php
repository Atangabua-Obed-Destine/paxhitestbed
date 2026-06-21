<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'category',
    ];

    /**
     * Categories
     */
    const CATEGORY_GENERAL = 'general';
    const CATEGORY_CLASS_SESSION = 'class_session';
    const CATEGORY_SCANNING = 'scanning';
    const CATEGORY_LOGBOOK = 'logbook';
    const CATEGORY_KIOSK = 'kiosk';

    /**
     * Get a setting value by key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        return static::castValue($setting->value, $setting->type);
    }

    /**
     * Set a setting value by key
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function setValue($key, $value)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return false;
        }

        $setting->update(['value' => $value]);
        return true;
    }

    /**
     * Get all settings in a category
     *
     * @param string $category
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getByCategory($category)
    {
        return static::where('category', $category)->get();
    }

    /**
     * Get all settings as key-value pairs
     *
     * @return array
     */
    public static function getAllAsArray()
    {
        $settings = [];
        
        foreach (static::all() as $setting) {
            $settings[$setting->key] = static::castValue($setting->value, $setting->type);
        }

        return $settings;
    }

    /**
     * Cast value to appropriate type
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    protected static function castValue($value, $type)
    {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Get typed value attribute
     */
    public function getTypedValueAttribute()
    {
        return static::castValue($this->value, $this->type);
    }

    /**
     * Scope for category
     */
    public function scopeInCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
