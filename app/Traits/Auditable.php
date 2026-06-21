<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * Boot the auditable trait.
     */
    public static function bootAuditable()
    {
        // Log creation
        static::created(function ($model) {
            $model->auditLog('created');
        });

        // Log updates
        static::updated(function ($model) {
            $model->auditLog('updated');
        });

        // Log deletions
        static::deleted(function ($model) {
            $model->auditLog('deleted');
        });
    }

    /**
     * Create an audit log entry.
     *
     * @param string $event
     * @param string|null $description
     * @return void
     */
    public function auditLog($event, $description = null)
    {
        // Get the authenticated user from any guard
        // Determine which guard to use based on the current route or request
        $user = null;
        
        // Check if we're in a student route (starts with /student)
        $isStudentRoute = Request::is('student*') || Request::is('*/student/*');
        
        if ($isStudentRoute && Auth::guard('student')->check()) {
            // Prioritize student guard for student routes
            $user = Auth::guard('student')->user();
        } elseif (Auth::guard('web')->check()) {
            // Use web guard for admin routes
            $user = Auth::guard('web')->user();
        } elseif (Auth::guard('student')->check()) {
            // Fallback to student guard if web is not authenticated
            $user = Auth::guard('student')->user();
        }
        
        // Get old values (for updates)
        $oldValues = null;
        if ($event === 'updated' && $this->wasChanged()) {
            $oldValues = [];
            foreach ($this->getChanges() as $key => $value) {
                if (!in_array($key, ['updated_at', 'remember_token'])) {
                    $oldValues[$key] = $this->getOriginal($key);
                }
            }
        }

        // Get new values
        $newValues = null;
        if ($event === 'created') {
            $newValues = $this->attributesToArray();
            // Remove sensitive data
            unset($newValues['password'], $newValues['remember_token']);
        } elseif ($event === 'updated' && $this->wasChanged()) {
            $newValues = [];
            foreach ($this->getChanges() as $key => $value) {
                if (!in_array($key, ['updated_at', 'remember_token', 'password'])) {
                    $newValues[$key] = $value;
                }
            }
        } elseif ($event === 'deleted') {
            $oldValues = $this->attributesToArray();
            unset($oldValues['password'], $oldValues['remember_token']);
        }

        // Skip if no changes
        if ($event === 'updated' && empty($newValues)) {
            return;
        }

        // Generate description if not provided
        if (!$description) {
            $modelName = class_basename(get_class($this));
            $description = "{$modelName} was {$event}";
            
            if (method_exists($this, 'getAuditDescription')) {
                // For certain models, ensure relationships are loaded before generating description
                if ($this instanceof \App\Models\StudentEnroll) {
                    $this->loadMissing(['student', 'program', 'semester', 'session', 'section']);
                } elseif ($this instanceof \App\Models\EnrollSubject) {
                    $this->loadMissing(['program', 'semester', 'section', 'subjects']);
                } elseif ($this instanceof \App\Models\ClassRoutine) {
                    $this->loadMissing(['teacher', 'subject', 'room', 'session', 'program', 'semester', 'section']);
                } elseif ($this instanceof \App\Models\Fee) {
                    $this->loadMissing(['category', 'studentEnroll', 'studentEnroll.student']);
                } elseif ($this instanceof \App\Models\Payroll) {
                    $this->loadMissing(['user']);
                } elseif ($this instanceof \App\Models\FeesMaster) {
                    $this->loadMissing(['category']);
                } elseif ($this instanceof \App\Models\Program) {
                    $this->loadMissing(['faculty']);
                } elseif ($this instanceof \App\Models\AccountingPeriod) {
                    $this->loadMissing(['fiscalYear']);
                } elseif ($this instanceof \App\Models\JournalEntryLine) {
                    $this->loadMissing(['account', 'journalEntry']);
                } elseif ($this instanceof \App\Models\MultiPayment) {
                    $this->loadMissing(['student']);
                } elseif ($this instanceof \App\Models\MultiPaymentDistribution) {
                    $this->loadMissing(['fee', 'fee.category']);
                } elseif ($this instanceof \App\Models\InstallmentPaymentReceipt) {
                    $this->loadMissing(['student', 'installment']);
                }
                
                $description = $this->getAuditDescription($event);
            }
        }

        AuditLog::create([
            'user_id' => $user ? $user->id : null,
            'user_type' => $user ? get_class($user) : null,
            'event' => $event,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'description' => $description,
        ]);
    }

    /**
     * Manually create a custom audit log entry.
     *
     * @param string $event
     * @param string $description
     * @param array|null $oldValues
     * @param array|null $newValues
     * @return void
     */
    public function customAuditLog($event, $description, $oldValues = null, $newValues = null)
    {
        // Get the authenticated user from any guard
        // Determine which guard to use based on the current route or request
        $user = null;
        
        // Check if we're in a student route (starts with /student)
        $isStudentRoute = Request::is('student*') || Request::is('*/student/*');
        
        if ($isStudentRoute && Auth::guard('student')->check()) {
            // Prioritize student guard for student routes
            $user = Auth::guard('student')->user();
        } elseif (Auth::guard('web')->check()) {
            // Use web guard for admin routes
            $user = Auth::guard('web')->user();
        } elseif (Auth::guard('student')->check()) {
            // Fallback to student guard if web is not authenticated
            $user = Auth::guard('student')->user();
        }

        AuditLog::create([
            'user_id' => $user ? $user->id : null,
            'user_type' => $user ? get_class($user) : null,
            'event' => $event,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'description' => $description,
        ]);
    }
}
