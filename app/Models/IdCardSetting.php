<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdCardSetting extends Model
{
    /** Validity in white at the top right of the card: how it has always been. */
    public const VALIDITY_TOP = 'top';

    /** Validity in black, as its own row under Date Issued. */
    public const VALIDITY_BELOW_ISSUED = 'below_issued';

    /** Barcode in a framed panel at the left of the card: how it has always been. */
    public const BARCODE_LEFT = 'left';

    /** Barcode with no border, moved right to sit beside the QR code. */
    public const BARCODE_BESIDE_QR = 'beside_qr';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'slug', 'title', 'subtitle', 'logo', 'background', 'website_url', 'validity', 'validity_position', 'address', 'prefix', 'student_photo', 'signature', 'barcode', 'barcode_position', 'status',
    ];

    public static function barcodePositions(): array
    {
        return [
            self::BARCODE_LEFT => __('Framed, at the left'),
            self::BARCODE_BESIDE_QR => __('No border, beside the QR code'),
        ];
    }

    public function barcodeBesideQr(): bool
    {
        return $this->barcode_position === self::BARCODE_BESIDE_QR;
    }

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
