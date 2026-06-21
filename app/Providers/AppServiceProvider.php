<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use App\Models\Web\TopbarSetting;
use App\Models\Web\SocialSetting;
use App\Models\ScheduleSetting;
use App\Models\Web\Page;
use App\Models\Language;
use App\Models\Setting;
use App\Models\Fee;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Payroll;
use App\Models\PaymentPlanPayment;
use App\Observers\FeeObserver;
use App\Observers\IncomeObserver;
use App\Observers\ExpenseObserver;
use App\Observers\PayrollObserver;
use App\Observers\PaymentPlanPaymentObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        Schema::defaultStringLength(191);
        Paginator::useBootstrap();

        // Register observers for auto-mapping transactions
        Fee::observe(FeeObserver::class);
        Income::observe(IncomeObserver::class);
        Expense::observe(ExpenseObserver::class);
        Payroll::observe(PayrollObserver::class);
        PaymentPlanPayment::observe(PaymentPlanPaymentObserver::class);

        // Share view for Common Data
        if (\Schema::hasTable('languages')) {
            $user_languages = Language::where('status', '1')->get();
        } else {
            $user_languages = collect();
        }
        
        if (\Schema::hasTable('settings')) {
            $setting = Setting::where('status', '1')->first();
        } else {
            $setting = null;
        }
        
        if (\Schema::hasTable('topbar_settings')) {
            $topbarSetting = TopbarSetting::where('status', '1')->first();
        } else {
            $topbarSetting = null;
        }
        
        if (\Schema::hasTable('social_settings')) {
            $socialSetting = SocialSetting::where('status', '1')->first();
        } else {
            $socialSetting = null;
        }
        
        if (\Schema::hasTable('schedule_settings')) {
            $schedule_setting = ScheduleSetting::where('slug', 'fees-schedule')->first();
        } else {
            $schedule_setting = null;
        }
        
        if (\Schema::hasTable('pages') && \Schema::hasTable('languages')) {
            $version = Language::version();
            if ($version) {
                $footer_pages = Page::where('language_id', $version->id)
                                    ->where('status', '1')
                                    ->orderBy('id', 'asc')
                                    ->get();
            } else {
                $footer_pages = collect();
            }
        } else {
            $footer_pages = collect();
        }

        // Set Time Zone
        if($setting) {
            Config::set('app.timezone', $setting->time_zone);
            date_default_timezone_set($setting->time_zone);

            try {
                \DB::statement("SET time_zone = '" . $setting->time_zone . "'");
            } catch (\Exception $e) {
                $offset = \Carbon\Carbon::now()->format('P');
                \DB::statement("SET time_zone = '" . $offset . "'");
            }
        }

        View::share(['setting' => $setting, 'user_languages' => $user_languages, 'schedule_setting' => $schedule_setting, 'topbarSetting' => $topbarSetting, 'socialSetting' => $socialSetting, 'footer_pages' => $footer_pages]);
    }
}
