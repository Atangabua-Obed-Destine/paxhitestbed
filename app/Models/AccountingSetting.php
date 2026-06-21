<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class AccountingSetting extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'label_fr',
        'description',
        'description_fr',
        'is_system',
        'validation_rules',
        'options',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'options' => 'array',
    ];

    /**
     * Setting types
     */
    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_DECIMAL = 'decimal';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DATE = 'date';
    const TYPE_ACCOUNT = 'account';
    const TYPE_JSON = 'json';

    /**
     * Setting groups
     */
    const GROUP_GENERAL = 'general';
    const GROUP_ACCOUNTS = 'accounts';
    const GROUP_FISCAL = 'fiscal';
    const GROUP_REPORTS = 'reports';
    const GROUP_AUTOMATION = 'automation';

    /**
     * Default settings keys
     */
    const KEY_DEFAULT_CURRENCY = 'default_currency';
    const KEY_DECIMAL_PLACES = 'decimal_places';
    const KEY_FISCAL_YEAR_START_MONTH = 'fiscal_year_start_month';
    const KEY_RETAINED_EARNINGS_ACCOUNT = 'retained_earnings_account_id';
    const KEY_INCOME_SUMMARY_ACCOUNT = 'income_summary_account_id';
    const KEY_SUSPENSE_ACCOUNT = 'suspense_account_id';
    const KEY_AUTO_POST_DEPRECIATION = 'auto_post_depreciation';
    const KEY_AUTO_POST_RECURRING = 'auto_post_recurring_entries';
    const KEY_REQUIRE_APPROVAL_ABOVE = 'require_approval_above_amount';

    /**
     * Get setting value by key
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
     * Set setting value by key
     */
    public static function setValue($key, $value)
    {
        $setting = static::where('key', $key)->first();
        
        if ($setting) {
            $setting->value = $value;
            $setting->save();
            return $setting;
        }

        return null;
    }

    /**
     * Cast value based on type
     */
    protected static function castValue($value, $type)
    {
        switch ($type) {
            case self::TYPE_INTEGER:
                return (int) $value;
            
            case self::TYPE_DECIMAL:
                return (float) $value;
            
            case self::TYPE_BOOLEAN:
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            
            case self::TYPE_DATE:
                return $value ? \Carbon\Carbon::parse($value) : null;
            
            case self::TYPE_ACCOUNT:
                return (int) $value;
            
            case self::TYPE_JSON:
                return json_decode($value, true);
            
            default:
                return $value;
        }
    }

    /**
     * Get all settings grouped
     */
    public static function getAllGrouped()
    {
        return static::all()->groupBy('group');
    }

    /**
     * Get settings by group
     */
    public static function getByGroup($group)
    {
        return static::where('group', $group)->get();
    }

    /**
     * Scope for non-system settings
     */
    public function scopeUserConfigurable($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope for system settings
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Get type label
     */
    public function getTypeLabel()
    {
        $labels = [
            self::TYPE_STRING => __('text'),
            self::TYPE_INTEGER => __('integer'),
            self::TYPE_DECIMAL => __('decimal'),
            self::TYPE_BOOLEAN => __('yes_no'),
            self::TYPE_DATE => __('date'),
            self::TYPE_ACCOUNT => __('account'),
            self::TYPE_JSON => __('json'),
        ];

        return $labels[$this->type] ?? $this->type;
    }

    /**
     * Get group label
     */
    public function getGroupLabel()
    {
        $labels = [
            self::GROUP_GENERAL => __('general'),
            self::GROUP_ACCOUNTS => __('accounts'),
            self::GROUP_FISCAL => __('fiscal_settings'),
            self::GROUP_REPORTS => __('reports'),
            self::GROUP_AUTOMATION => __('automation'),
        ];

        return $labels[$this->group] ?? $this->group;
    }

    /**
     * Get the account if type is account
     */
    public function getAccount()
    {
        if ($this->type !== self::TYPE_ACCOUNT) {
            return null;
        }

        return ChartOfAccount::find($this->value);
    }

    /**
     * Validate value before save
     */
    public function validateValue($value)
    {
        if (!$this->validation_rules) {
            return true;
        }

        $validator = \Validator::make(
            ['value' => $value],
            ['value' => $this->validation_rules]
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first('value'));
        }

        return true;
    }

    /**
     * Get all setting types
     */
    public static function getTypes()
    {
        return [
            self::TYPE_STRING => __('text'),
            self::TYPE_INTEGER => __('integer'),
            self::TYPE_DECIMAL => __('decimal'),
            self::TYPE_BOOLEAN => __('yes_no'),
            self::TYPE_DATE => __('date'),
            self::TYPE_ACCOUNT => __('account'),
            self::TYPE_JSON => __('json'),
        ];
    }

    /**
     * Get all setting groups
     */
    public static function getGroups()
    {
        return [
            self::GROUP_GENERAL => __('general'),
            self::GROUP_ACCOUNTS => __('accounts'),
            self::GROUP_FISCAL => __('fiscal_settings'),
            self::GROUP_REPORTS => __('reports'),
            self::GROUP_AUTOMATION => __('automation'),
        ];
    }

    /**
     * Seed default settings
     */
    public static function seedDefaults()
    {
        $defaults = [
            [
                'key' => self::KEY_DEFAULT_CURRENCY,
                'value' => 'XAF',
                'type' => self::TYPE_STRING,
                'group' => self::GROUP_GENERAL,
                'label' => 'Default Currency',
                'label_fr' => 'Devise par défaut',
                'is_system' => false,
            ],
            [
                'key' => self::KEY_DECIMAL_PLACES,
                'value' => '2',
                'type' => self::TYPE_INTEGER,
                'group' => self::GROUP_GENERAL,
                'label' => 'Decimal Places',
                'label_fr' => 'Décimales',
                'is_system' => false,
                'validation_rules' => 'integer|min:0|max:6',
            ],
            [
                'key' => self::KEY_FISCAL_YEAR_START_MONTH,
                'value' => '1',
                'type' => self::TYPE_INTEGER,
                'group' => self::GROUP_FISCAL,
                'label' => 'Fiscal Year Start Month',
                'label_fr' => 'Mois de début de l\'exercice',
                'is_system' => false,
                'validation_rules' => 'integer|min:1|max:12',
            ],
            [
                'key' => self::KEY_AUTO_POST_DEPRECIATION,
                'value' => '0',
                'type' => self::TYPE_BOOLEAN,
                'group' => self::GROUP_AUTOMATION,
                'label' => 'Auto-post Depreciation',
                'label_fr' => 'Amortissement automatique',
                'is_system' => false,
            ],
            [
                'key' => self::KEY_AUTO_POST_RECURRING,
                'value' => '0',
                'type' => self::TYPE_BOOLEAN,
                'group' => self::GROUP_AUTOMATION,
                'label' => 'Auto-post Recurring Entries',
                'label_fr' => 'Écritures récurrentes automatiques',
                'is_system' => false,
            ],
            [
                'key' => self::KEY_REQUIRE_APPROVAL_ABOVE,
                'value' => '1000000',
                'type' => self::TYPE_DECIMAL,
                'group' => self::GROUP_GENERAL,
                'label' => 'Require Approval Above Amount',
                'label_fr' => 'Approbation requise au-dessus du montant',
                'is_system' => false,
            ],
        ];

        foreach ($defaults as $setting) {
            static::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $key = $this->key ?? 'N/A';
        $label = $this->label ?? 'N/A';
        
        return "Accounting Setting {$event}: [{$key}] {$label}";
    }
}
