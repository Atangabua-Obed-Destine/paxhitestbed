<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Traits\Auditable;

class Payroll extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'basic_salary', 'salary_type', 'total_earning', 'total_allowance', 'bonus', 'total_deduction', 'gross_salary', 'tax', 'employer_tax', 'net_salary', 'total_cost', 'salary_month', 'pay_date', 'payment_method', 'payment_account_id', 'bank_account_id', 'note', 'status', 'created_by', 'updated_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(StaffBankAccount::class, 'bank_account_id');
    }

    public function details()
    {
        return $this->hasMany(PayrollDetail::class, 'payroll_id', 'id');
    }
    
    /**
     * Custom audit description for better readability
     */
    public function getAuditDescription($event)
    {
        // Load user relationship if not loaded
        try {
            if (!$this->relationLoaded('user') && $this->user_id) {
                $this->load('user');
            }
        } catch (\Exception $e) {
            // Silently handle loading errors
        }
        
        $netSalary = number_format($this->net_salary ?? 0, 2);
        $month = $this->salary_month ?? 'Unknown';
        
        // Get user name with fallback
        $userName = 'Unknown User';
        if ($this->user) {
            $userName = $this->user->name ?? 'User #' . $this->user_id;
        } elseif ($this->user_id) {
            // Fallback to direct query
            $user = \App\User::find($this->user_id);
            $userName = $user ? ($user->name ?? 'User #' . $this->user_id) : 'User #' . $this->user_id;
        }
        
        return "Payroll {$event} for {$userName}: {$month}, Net Salary: {$netSalary}";
    }
}
