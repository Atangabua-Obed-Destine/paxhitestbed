<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * This institution's connection to its body's EdutrustPay console.
 *
 * One row. Use current() rather than querying directly, so there is one place
 * that decides what "the settings" means.
 */
class EdutrustPaySetting extends Model
{
    protected $table = 'edutrustpay_settings';

    protected $fillable = [
        'enabled', 'endpoint', 'institution_ref', 'key_id', 'secret_ciphertext',
        'last_tested_at', 'last_test_ok', 'last_test_message', 'updated_by',
    ];

    /**
     * Never serialise the secret — not into JSON, not onto an exception page.
     */
    protected $hidden = ['secret_ciphertext'];

    protected $casts = [
        'enabled' => 'boolean',
        'last_test_ok' => 'boolean',
        'last_tested_at' => 'datetime',
        // Encrypted on write, decrypted on read, using APP_KEY.
        'secret_ciphertext' => 'encrypted',
    ];

    public static function current(): self
    {
        return static::firstOrNew([]);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function secret(): ?string
    {
        return $this->secret_ciphertext ?: null;
    }

    public function hasSecret(): bool
    {
        return filled($this->secret_ciphertext);
    }

    /**
     * Enough to recognise a secret without revealing it.
     */
    public function secretHint(): ?string
    {
        $secret = $this->secret();

        return $secret ? str_repeat('•', 8) . substr($secret, -4) : null;
    }

    public function isConfigured(): bool
    {
        return filled($this->endpoint) && filled($this->institution_ref)
            && filled($this->key_id) && $this->hasSecret();
    }
}
