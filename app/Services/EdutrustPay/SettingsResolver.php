<?php

namespace App\Services\EdutrustPay;

use App\Models\EdutrustPaySetting;

/**
 * Where the EdutrustPay connection settings actually come from.
 *
 * Two sources, in this order:
 *
 *   1. The database — set through the settings screen.
 *   2. config/edutrustpay.php, which reads .env.
 *
 * The database wins, and .env remains a working fallback so an installation
 * configured before this screen existed keeps reporting without anyone touching
 * it. Nothing that worked yesterday stops working today.
 *
 * Everything needing credentials goes through here rather than reading config()
 * directly, so there is one place that knows the precedence — and one place to
 * look when somebody asks why a saved key is not being picked up.
 */
class SettingsResolver
{
    /**
     * Resolved settings, or null when this institution is not configured.
     *
     * @return array{
     *     enabled: bool, endpoint: string, institution_ref: string,
     *     key_id: string, secret: string, source: string
     * }|null
     */
    public function resolve(): ?array
    {
        $row = EdutrustPaySetting::current();

        if ($row->exists && $row->isConfigured()) {
            return [
                'enabled' => (bool) $row->enabled,
                'endpoint' => (string) $row->endpoint,
                'institution_ref' => (string) $row->institution_ref,
                'key_id' => (string) $row->key_id,
                'secret' => (string) $row->secret(),
                'source' => 'database',
            ];
        }

        foreach (['endpoint', 'institution_ref', 'key_id', 'secret'] as $key) {
            if (blank(config('edutrustpay.'.$key))) {
                return null;
            }
        }

        return [
            'enabled' => (bool) config('edutrustpay.enabled', false),
            'endpoint' => (string) config('edutrustpay.endpoint'),
            'institution_ref' => (string) config('edutrustpay.institution_ref'),
            'key_id' => (string) config('edutrustpay.key_id'),
            'secret' => (string) config('edutrustpay.secret'),
            'source' => 'env',
        ];
    }

    /**
     * Configured AND switched on.
     *
     * Configured but deliberately quiet is not the same as unconfigured, and
     * neither should send.
     */
    public function isReporting(): bool
    {
        $settings = $this->resolve();

        return $settings !== null && $settings['enabled'];
    }

    public function isConfigured(): bool
    {
        return $this->resolve() !== null;
    }
}
