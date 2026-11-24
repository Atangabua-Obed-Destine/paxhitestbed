<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A loan or salary advance to a staff member, repaid by a deduction on each
 * payroll from its start month until nothing is left.
 *
 * What has been repaid is not stored: it is the sum of this loan's lines on
 * payrolls that are paid. Reversing a payment therefore puts the instalment
 * back on the balance with no extra bookkeeping, and nothing can drift.
 */
class StaffLoan extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id', 'kind', 'title', 'amount', 'instalment', 'start_month', 'given_on', 'status', 'note',
        'journal_entry_id', 'reversal_entry_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_month' => 'date',
        'given_on' => 'date',
        'amount' => 'float',
        'instalment' => 'float',
    ];

    public const KINDS = ['loan' => 'Loan', 'advance' => 'Salary advance'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lines()
    {
        return $this->hasMany(PayrollDetail::class, 'staff_loan_id');
    }

    /** Taken on payrolls that have been paid. */
    public function repaid(): float
    {
        return round((float) DB::table('payroll_details')
            ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
            ->where('payroll_details.staff_loan_id', $this->id)
            ->where('payrolls.status', 1)
            ->sum('payroll_details.amount'), 2);
    }

    /** Set aside on payrolls generated but not yet paid, other than $exceptPayrollId. */
    public function pending(?int $exceptPayrollId = null): float
    {
        return round((float) DB::table('payroll_details')
            ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
            ->where('payroll_details.staff_loan_id', $this->id)
            ->where('payrolls.status', 0)
            ->when($exceptPayrollId, fn ($q) => $q->where('payrolls.id', '!=', $exceptPayrollId))
            ->sum('payroll_details.amount'), 2);
    }

    public function balance(): float
    {
        return round(max(0, $this->amount - $this->repaid()), 2);
    }

    /** What a payroll may still take, given what other unpaid payrolls already hold. */
    public function available(?int $exceptPayrollId = null): float
    {
        return round(max(0, $this->amount - $this->repaid() - $this->pending($exceptPayrollId)), 2);
    }

    /** The instalment due on a month's payroll: nothing before the start month or once cleared. */
    public function dueFor($salaryMonth, ?int $exceptPayrollId = null): float
    {
        if ($this->status !== 'active') {
            return 0.0;
        }

        $month = Carbon::parse($salaryMonth)->startOfMonth();
        if ($month->lt(Carbon::parse($this->start_month)->startOfMonth())) {
            return 0.0;
        }

        return round(min($this->instalment, $this->available($exceptPayrollId)), 2);
    }

    public function isCleared(): bool
    {
        return $this->balance() <= 0.004;
    }

    /** Active loans of a staff member with something still to take for a month. */
    public static function dueLines(int $userId, $salaryMonth, ?int $exceptPayrollId = null): array
    {
        $lines = [];

        foreach (static::where('user_id', $userId)->where('status', 'active')->orderBy('id')->get() as $loan) {
            $due = $loan->dueFor($salaryMonth, $exceptPayrollId);
            if ($due > 0) {
                $lines[] = ['title' => $loan->title, 'amount' => $due, 'loan_id' => $loan->id];
            }
        }

        return $lines;
    }

    public function getAuditDescription($event)
    {
        return "Staff loan {$event}: {$this->title} - Amount: {$this->amount}";
    }
}
