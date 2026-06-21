<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\SecuritySetting;

class StrongPassword implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Get security settings
        $minLength = SecuritySetting::getValue('min_password_length', 10);
        $requireComplexity = SecuritySetting::getValue('require_password_complexity', true);

        // Check minimum length
        if (strlen($value) < $minLength) {
            $fail("The :attribute must be at least {$minLength} characters.");
            return;
        }

        // If complexity is required, validate
        if ($requireComplexity) {
            $hasUpperCase = preg_match('/[A-Z]/', $value);
            $hasLowerCase = preg_match('/[a-z]/', $value);
            $hasNumber = preg_match('/[0-9]/', $value);
            $hasSpecialChar = preg_match('/[^A-Za-z0-9]/', $value);

            if (!$hasUpperCase) {
                $fail('The :attribute must contain at least one uppercase letter.');
                return;
            }

            if (!$hasLowerCase) {
                $fail('The :attribute must contain at least one lowercase letter.');
                return;
            }

            if (!$hasNumber) {
                $fail('The :attribute must contain at least one number.');
                return;
            }

            if (!$hasSpecialChar) {
                $fail('The :attribute must contain at least one special character.');
                return;
            }
        }
    }
}
