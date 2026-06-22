<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The applicant LOGIN ACCOUNT. One applicant owns many Application submissions
 * (one per degree type / intake). Authentication for the `applicant` guard runs
 * against this model.
 */
class Applicant extends Authenticatable
{
    use Auditable, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'email_verified_at',
        'portal_last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'portal_last_login_at' => 'datetime',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class, 'applicant_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}
