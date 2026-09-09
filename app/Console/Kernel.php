<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\ScheduleSetting;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Schedule the fees reminder command
        $schedule_setting = ScheduleSetting::where('slug', 'fees-schedule')->first();

        if(isset($schedule_setting)){
            if($schedule_setting->time){
                $schedule->command('fees:reminder')
                     ->dailyAt($schedule_setting->time);
            }else{
                $schedule->command('fees:reminder')
                     ->dailyAt('00:01');
            }
        }

        $schedule->command('notice:send')
                ->dailyAt('01:01');

        $schedule->command('content:send')
                ->dailyAt('02:01');
        
        // End expired class sessions and mark incomplete attendances as absent
        // Runs at 11:59 PM every day
        $schedule->command('class-sessions:end-expired')
                ->dailyAt('23:59');

        /*
         * ---------------------------------------------------------------
         * EdutrustPay reporting
         * ---------------------------------------------------------------
         *
         * This institution PUSHES a signed monthly summary to its body's
         * console. Nothing reaches in here: outbound HTTPS only, no inbound
         * endpoint, no tunnel.
         *
         * Three separate jobs on purpose:
         *
         *   report     builds last month once the books have had time to settle
         *   flush      delivers whatever is waiting, often, because this site's
         *              connectivity comes and goes
         *   heartbeat  says "still here" on the days there is nothing to send,
         *              without which a quiet month and a dead server look
         *              identical from the console
         *
         * All three are no-ops unless EDUTRUSTPAY_ENABLED is true, so leaving
         * them scheduled on an unconfigured install costs nothing.
         */
        $schedule->command('edutrustpay:report')
                ->monthlyOn(4, '03:00')
                ->withoutOverlapping();

        $schedule->command('edutrustpay:flush')
                ->hourly()
                ->withoutOverlapping();

        $schedule->command('edutrustpay:heartbeat')
                ->dailyAt('05:30');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
