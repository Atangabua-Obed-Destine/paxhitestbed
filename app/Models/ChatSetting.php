<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row configuration for the context-aware chat module.
 */
class ChatSetting extends Model
{
    use HasFactory, Auditable;

    /** Surfaces the widget can be shown on, mapped to their enable column. */
    public const SURFACES = [
        'web' => 'enabled_web',
        'application' => 'enabled_application',
        'student' => 'enabled_student',
        'admin' => 'enabled_admin',
    ];

    protected $fillable = [
        'title',
        'is_enabled',
        'enabled_web',
        'enabled_application',
        'enabled_student',
        'enabled_admin',
        'provider',
        'model',
        'temperature',
        'max_output_tokens',
        'max_tool_calls',
        'system_prompt',
        'greeting_web',
        'greeting_application',
        'greeting_student',
        'greeting_admin',
        'rate_limit_per_minute',
        'history_retention_days',
        'escalation_enabled',
        'status',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'enabled_web' => 'boolean',
        'enabled_application' => 'boolean',
        'enabled_student' => 'boolean',
        'enabled_admin' => 'boolean',
        'escalation_enabled' => 'boolean',
        'status' => 'boolean',
        'temperature' => 'float',
        'max_output_tokens' => 'integer',
        'max_tool_calls' => 'integer',
        'rate_limit_per_minute' => 'integer',
        'history_retention_days' => 'integer',
    ];

    /**
     * The one settings row, created on demand so the module never hard-fails
     * on a fresh install.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::create(['title' => 'Ask CATUC']);
    }

    /** Is the widget switched on for this surface? */
    public function enabledFor(string $surface): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        $column = static::SURFACES[$surface] ?? null;

        return $column ? (bool) $this->{$column} : false;
    }

    /** The opening line shown when a thread starts on this surface. */
    public function greetingFor(string $surface): ?string
    {
        return $this->{'greeting_' . $surface} ?? null;
    }
}
