<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Setting key-value structure
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 50)->default('string'); // string, integer, float, boolean, json
            $table->text('description')->nullable();
            
            // Category for grouping
            $table->string('category', 50)->default('general');
            
            $table->timestamps();
        });
        
        // Insert default settings
        $this->insertDefaultSettings();
    }
    
    /**
     * Insert default attendance settings
     */
    private function insertDefaultSettings()
    {
        $settings = [
            // Class Session Settings
            [
                'key' => 'minimum_class_duration_percentage',
                'value' => '70',
                'type' => 'integer',
                'description' => 'Minimum percentage of scheduled class duration required for lecturer to be marked Present',
                'category' => 'class_session'
            ],
            [
                'key' => 'late_threshold_percentage',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Percentage of class duration after which students are marked as Late',
                'category' => 'class_session'
            ],
            [
                'key' => 'scan_cooldown_seconds',
                'value' => '60',
                'type' => 'integer',
                'description' => 'Minimum seconds between scans for the same student to avoid duplicates',
                'category' => 'scanning'
            ],
            [
                'key' => 'auto_clock_out_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Automatically clock out remaining students when class ends',
                'category' => 'class_session'
            ],
            [
                'key' => 'class_end_on_first_clock_out',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Set class actual end time when first student clocks out',
                'category' => 'class_session'
            ],
            [
                'key' => 'allow_extra_classes',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Allow lecturers to add extra/unscheduled classes',
                'category' => 'class_session'
            ],
            [
                'key' => 'require_logbook_completion',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Require logbook to be filled before lecturer attendance is synced',
                'category' => 'logbook'
            ],
            [
                'key' => 'lecturer_attendance_auto_sync',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Automatically sync lecturer attendance to staff_hourly_attendances table',
                'category' => 'class_session'
            ],
            [
                'key' => 'student_multi_scan_mode',
                'value' => 'toggle',
                'type' => 'string',
                'description' => 'How student scans work: toggle (in/out), first_in (only first scan counts)',
                'category' => 'scanning'
            ],
            [
                'key' => 'kiosk_auto_refresh_seconds',
                'value' => '30',
                'type' => 'integer',
                'description' => 'Auto-refresh interval for kiosk stats display',
                'category' => 'kiosk'
            ],
            [
                'key' => 'allow_class_rep_selection',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Allow lecturer to select class representative from student list',
                'category' => 'logbook'
            ],
            [
                'key' => 'show_hod_on_logbook',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Display HOD name on logbook based on academic department',
                'category' => 'logbook'
            ],
        ];
        
        $now = now();
        foreach ($settings as &$setting) {
            $setting['created_at'] = $now;
            $setting['updated_at'] = $now;
        }
        
        \DB::table('attendance_settings')->insert($settings);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance_settings');
    }
}
