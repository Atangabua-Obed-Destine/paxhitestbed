<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Models\Exam;
use App\Observers\ExamObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * The model observers for the application.
     *
     * @var array
     */
    protected $observers = [
        Exam::class => [ExamObserver::class],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
        Queue::failing(function (JobFailed $event) {
            Log::error('Queue job failed', ['connection' => $event->connectionName, 'job' => $event->job->getName(), 'exception' => $event->exception->getMessage()]);
        });

        // Keep permissions current on every deploy and every new install.
        //
        // Permissions live in seeders, and deploying code never runs a seeder
        // — which is how the Daybook, Tax Remittance and Academic Health
        // screens were visible on the testbed and missing on production. Every
        // deploy and every install already runs `php artisan migrate`, so the
        // sync rides on that. It runs after migrate finishes even when there
        // was nothing to migrate, because a new permission often arrives with
        // no migration at all.
        //
        // Skipped for --pretend (nothing is meant to change), when migrate
        // failed, and when PERMISSIONS_SYNC_ON_MIGRATE=false.
        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            if (!in_array($event->command, ['migrate', 'migrate:fresh', 'migrate:refresh'], true)) {
                return;
            }

            if ($event->exitCode !== 0 || config('permission.sync_on_migrate', true) === false) {
                return;
            }

            if ($event->input->hasOption('pretend') && $event->input->getOption('pretend')) {
                return;
            }

            $event->output->writeln('');
            $event->output->writeln('<info>Syncing permissions...</info>');

            Artisan::call('permissions:sync', [], $event->output);
        });
    }
}
