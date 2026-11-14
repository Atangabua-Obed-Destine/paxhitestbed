<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdCardSetting extends Model
{
    /** Validity in white at the top right of the card: how it has always been. */
    public const VALIDITY_TOP = 'top';

    /** Validity in black, as its own row under Date Issued. */
    public const VALIDITY_BELOW_ISSUED = 'below_issued';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'slug', 'title', 'subtitle', 'logo', 'background', 'website_url', 'validity', 'validity_position', 'address', 'prefix', 'student_photo', 'signature', 'barcode', 'status',
    ];

    public static function validityPositions(): array
    {
        return [
            self::VALIDITY_TOP => __('Top right (white)'),
            self::VALIDITY_BELOW_ISSUED => __('Under Date Issued (black)'),
        ];
    }

    public function validityBelowIssued(): bool
    {
        return $this->validity_position === self::VALIDITY_BELOW_ISSUED;
    }
}
