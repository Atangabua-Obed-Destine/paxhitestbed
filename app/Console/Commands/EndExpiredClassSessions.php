<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClassSession;
use Carbon\Carbon;

class EndExpiredClassSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'class-sessions:end-expired {--date= : The date to process (defaults to today)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'End all class sessions that are still in progress but have passed their scheduled end time. Marks students who clocked in but never clocked out as Absent.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $dateString = $date->format('Y-m-d');
        
        $this->info("Processing expired class sessions for {$dateString}...");
        
        // Find all sessions that are still in progress for the given date
        $expiredSessions = ClassSession::where('status', ClassSession::STATUS_IN_PROGRESS)
            ->where('date', $dateString)
            ->get();
        
        if ($expiredSessions->isEmpty()) {
            $this->info('No expired sessions found.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$expiredSessions->count()} expired session(s).");
        
        $processed = 0;
        foreach ($expiredSessions as $session) {
            // Calculate the scheduled end time
            $scheduledEndTime = $session->scheduled_end_time;
            if ($scheduledEndTime instanceof Carbon) {
                $endDateTime = $scheduledEndTime;
            } else {
                $endDateTime = Carbon::parse($dateString . ' ' . $scheduledEndTime);
            }
            
            // Only end if we're past the scheduled end time
            if (now()->gt($endDateTime)) {
                $subjectName = $session->subject->subject_name ?? 'Unknown';
                $this->line("  - Ending session ID {$session->id}: {$subjectName} ({$session->scheduled_start_time} - {$session->scheduled_end_time})");
                
                // End the session at the scheduled end time
                $session->end($endDateTime);
                $processed++;
            }
        }
        
        $this->info("Processed {$processed} session(s).");
        $this->info('All incomplete student attendances have been marked as Absent.');
        
        return Command::SUCCESS;
    }
}
