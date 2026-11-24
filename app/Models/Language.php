<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Session;

class Language extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'code', 'description', 'direction', 'default', 'status',
    ];

    /** Answers already looked up during this web request, by locale. */
    protected static array $versionMemo = [];

    protected static function booted()
    {
        // A language edited during the request is read again on the next call.
        static::saved(fn () => static::$versionMemo = []);
        static::deleted(fn () => static::$versionMemo = []);
    }

    // Language Version
    //
    // Asked for five or more times on every admin page (header, master, footer,
    // menus); a web request now reads it once per locale. Console commands and
    // queue workers live for hours, so they still read it fresh every time.
    static public function version()
    {
        $locale = Session()->has('locale') ? (string) Session()->get('locale') : null;
        $memoise = !app()->runningInConsole();
        $key = $locale ?? ':default';

        if ($memoise && array_key_exists($key, static::$versionMemo)) {
            return static::$versionMemo[$key];
        }

        if ($locale !== null) {
            $version = Language::where('code', $locale)->first();
        } else {
            $version = Language::where('default', 1)->first();
        }

        if ($memoise) {
            static::$versionMemo[$key] = $version;
        }

        return $version;
    }
}
