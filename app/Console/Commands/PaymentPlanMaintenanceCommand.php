<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentPlanMaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment-plan:maintain {--update-status : Update overdue installment statuses} {--apply-late-fees : Apply late fees to overdue installments} {--send-reminders : Send payment reminders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Maintain payment plans: update statuses, apply late fees, and send reminders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Payment Plan Maintenance...');
        
        $updateStatus = $this->option('update-status');
        $applyLateFees = $this->option('apply-late-fees');
        $sendReminders = $this->option('send-reminders');
        
        // If no options specified, run all tasks
        if (!$updateStatus && !$applyLateFees && !$sendReminders) {
            $updateStatus = true;
            $applyLateFees = true;
            $sendReminders = true;
        }
        
        if ($updateStatus) {
            $this->updateOverdueStatuses();
        }
        
        if ($applyLateFees) {
            $this->applyLateFees();
        }
        
        if ($sendReminders) {
            $this->sendPaymentReminders();
        }
        
        $this->info('Payment Plan Maintenance completed successfully!');
        
        return 0;
    }
    
    /**
     * Update overdue installment statuses
     */
    protected function updateOverdueStatuses()
    {
        $this->info('Updating overdue installment statuses...');
        
        // Get active payment plans
        $activePlans = PaymentPlan::where('status', 'active')->get();
        
        $updatedCount = 0;
        
        foreach ($activePlans as $plan) {
            foreach ($plan->installments as $installment) {
                if ($installment->status !== 'paid' && $installment->is_overdue) {
                    $installment->updateStatus();
                    $updatedCount++;
                }
            }
        }
        
        $this->info("✓ Updated {$updatedCount} installment statuses to overdue");
        Log::info("Payment Plan Maintenance: Updated {$updatedCount} installment statuses");
    }
    
    /**
     * Apply late fees to overdue installments
     */
    protected function applyLateFees()
    {
        $this->info('Applying late fees to overdue installments...');
        
        // Get overdue installments that don't have late fees yet
        $overdueInstallments = PaymentPlanInstallment::with('paymentPlan')
            ->where('status', 'overdue')
            ->where('late_fee', 0)
            ->orWhereNull('late_fee')
            ->get();
        
        $appliedCount = 0;
        
        foreach ($overdueInstallments as $installment) {
            // Check if payment plan is still active
            if ($installment->paymentPlan && $installment->paymentPlan->isActive()) {
                if ($installment->applyLateFee()) {
                    $appliedCount++;
                    $this->line("  Applied late fee to Installment #{$installment->installment_number} of Plan #{$installment->payment_plan_id}");
                }
            }
        }
        
        $this->info("✓ Applied late fees to {$appliedCount} installments");
        Log::info("Payment Plan Maintenance: Applied {$appliedCount} late fees");
    }
    
    /**
     * Send payment reminders for upcoming installments
     */
    protected function sendPaymentReminders()
    {
        $this->info('Sending payment reminders...');
        
        // Get installments due in next 7 days
        $upcomingInstallments = PaymentPlanInstallment::with(['paymentPlan.student', 'paymentPlan.fee.category'])
            ->whereHas('paymentPlan', function($query) {
                $query->where('status', 'active');
            })
            ->whereIn('status', ['pending', 'partial'])
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->get();
        
        $reminderCount = 0;
        
        foreach ($upcomingInstallments as $installment) {
            $plan = $installment->paymentPlan;
            $student = $plan->student;
            
            if ($student && $student->email) {
                try {
                    // Here you would send an email/SMS reminder
                    // For now, we'll just log it
                    $daysUntilDue = Carbon::parse($installment->due_date)->diffInDays(now());
                    
                    $this->line("  Reminder for: {$student->first_name} {$student->last_name}");
                    $this->line("    Email: {$student->email}");
                    $this->line("    Installment #{$installment->installment_number}");
                    $this->line("    Amount: " . number_format($installment->remaining_balance, 2));
                    $this->line("    Due in: {$daysUntilDue} days");
                    
                    // Log reminder
                    Log::info("Payment Reminder sent to {$student->email} for Installment #{$installment->id}");
                    
                    $reminderCount++;
                    
                    // Uncomment when email notification is set up:
                    // Notification::send($student, new PaymentReminderNotification($installment));
                    
                } catch (\Exception $e) {
                    $this->error("  Failed to send reminder: " . $e->getMessage());
                    Log::error("Payment Reminder failed: " . $e->getMessage());
                }
            }
        }
        
        $this->info("✓ Sent {$reminderCount} payment reminders");
        Log::info("Payment Plan Maintenance: Sent {$reminderCount} reminders");
    }
}
