<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Traits\Auditable;
use Carbon\Carbon;

class DynamicPopup extends Model
{
    use HasFactory, Auditable;

    /**
     * Target area constants
     */
    public const AREA_FRONT_WEB = 'front_web';
    public const AREA_STUDENT_PORTAL = 'student_portal';
    public const AREA_APPLICANT_PORTAL = 'applicant_portal';
    public const AREA_ADMIN_PORTAL = 'admin_portal';
    public const AREA_LOGIN_PAGES = 'login_pages';
    public const AREA_ALL = 'all';

    /**
     * Display frequency constants
     */
    public const FREQ_ONCE_EVER = 'once_ever';
    public const FREQ_ONCE_SESSION = 'once_session';
    public const FREQ_ONCE_DAY = 'once_day';
    public const FREQ_ALWAYS = 'always';

    /**
     * Position constants
     */
    public const POS_CENTER = 'center';
    public const POS_BOTTOM_RIGHT = 'bottom_right';
    public const POS_BOTTOM_LEFT = 'bottom_left';
    public const POS_TOP_RIGHT = 'top_right';

    protected $fillable = [
        'title',
        'summary',
        'image',
        'button_text',
        'button_color',
        'button_text_color',
        'link',
        'target_areas',
        'display_frequency',
        'popup_position',
        'start_date',
        'end_date',
        'priority',
        'is_dismissible',
        'is_pinned',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'target_areas' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_dismissible' => 'boolean',
        'is_pinned' => 'boolean',
        'status' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get all available target areas
     */
    public static function getTargetAreas(): array
    {
        return [
            self::AREA_ALL => __('All Areas'),
            self::AREA_FRONT_WEB => __('Front Website'),
            self::AREA_STUDENT_PORTAL => __('Student Portal'),
            self::AREA_APPLICANT_PORTAL => __('Applicant Portal'),
            self::AREA_ADMIN_PORTAL => __('Admin Portal'),
            self::AREA_LOGIN_PAGES => __('Login Pages'),
        ];
    }

    /**
     * Get all display frequencies
     */
    public static function getDisplayFrequencies(): array
    {
        return [
            self::FREQ_ONCE_SESSION => __('Once per session'),
            self::FREQ_ONCE_DAY => __('Once per day'),
            self::FREQ_ONCE_EVER => __('Once ever (remember forever)'),
            self::FREQ_ALWAYS => __('Every page load'),
        ];
    }

    /**
     * Get all popup positions
     */
    public static function getPopupPositions(): array
    {
        return [
            self::POS_CENTER => __('Center Modal'),
            self::POS_BOTTOM_RIGHT => __('Bottom Right'),
            self::POS_BOTTOM_LEFT => __('Bottom Left'),
            self::POS_TOP_RIGHT => __('Top Right'),
        ];
    }

    /**
     * Creator relationship
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Updater relationship
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope: Active popups
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope: Within date range (currently valid)
     */
    public function scopeCurrentlyValid($query)
    {
        $now = Carbon::now();
        
        return $query->where(function ($q) use ($now) {
            $q->whereNull('start_date')
              ->orWhere('start_date', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', $now);
        });
    }

    /**
     * Scope: For specific target area
     */
    public function scopeForArea($query, string $area)
    {
        return $query->where(function ($q) use ($area) {
            $q->whereJsonContains('target_areas', $area)
              ->orWhereJsonContains('target_areas', self::AREA_ALL);
        });
    }

    /**
     * Scope: Order by priority (highest first)
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * Get active popups for a specific area
     */
    public static function getPopupsForArea(string $area): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()
            ->currentlyValid()
            ->forArea($area)
            ->byPriority()
            ->get();
    }

    /**
     * Check if popup is currently active and valid
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->status) {
            return false;
        }

        $now = Carbon::now();

        if ($this->start_date && $this->start_date > $now) {
            return false;
        }

        if ($this->end_date && $this->end_date < $now) {
            return false;
        }

        return true;
    }

    /**
     * Check if popup should show for given area
     */
    public function shouldShowForArea(string $area): bool
    {
        if (!$this->isCurrentlyActive()) {
            return false;
        }

        $areas = $this->target_areas ?? [];
        
        return in_array(self::AREA_ALL, $areas) || in_array($area, $areas);
    }

    /**
     * Get image URL
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return asset('uploads/dynamic-popups/' . $this->image);
    }

    /**
     * Get status badge HTML
     */
    public function getStatusBadgeAttribute(): string
    {
        if ($this->status) {
            if ($this->isCurrentlyActive()) {
                return '<span class="badge badge-success">' . __('Active') . '</span>';
            }
            
            $now = Carbon::now();
            if ($this->start_date && $this->start_date > $now) {
                return '<span class="badge badge-info">' . __('Scheduled') . '</span>';
            }
            if ($this->end_date && $this->end_date < $now) {
                return '<span class="badge badge-secondary">' . __('Expired') . '</span>';
            }
        }
        
        return '<span class="badge badge-danger">' . __('Inactive') . '</span>';
    }

    /**
     * Get target areas as badges
     */
    public function getTargetAreasBadgesAttribute(): string
    {
        $areas = $this->target_areas ?? [];
        $allAreas = self::getTargetAreas();
        
        $badges = [];
        foreach ($areas as $area) {
            $label = $allAreas[$area] ?? $area;
            $color = $area === self::AREA_ALL ? 'primary' : 'secondary';
            $badges[] = '<span class="badge badge-' . $color . ' mr-1">' . $label . '</span>';
        }
        
        return implode('', $badges);
    }
}
