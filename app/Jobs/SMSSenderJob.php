<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\SMS\SMSServiceFactory;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Exception;

class SMSSenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Number of times the job should be attempted
    public $backoff = 1; // Delay between retries in seconds
    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $smsService = SMSServiceFactory::create($this->data['sms_provider']);
            $smsService->sendSMS($this->data['phone'], $this->data['message']);

        } catch (Exception $e) {
            // Log the error or handle the exception
            Log::error('SMS sending failed: ' . $e->getMessage());

            // Re-throw the exception to trigger the retry
            throw $e;
        }
    }

    // Optionally, define retry logic based on exception type
    public function retryAfter()
    {
        return now()->addSeconds($this->backoff);
    }
}
