<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Flasher\Laravel\Facade\Flasher;

class AttendanceSettingController extends Controller
{
    protected $title, $route, $view, $path, $access;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = 'Class Session & Kiosk Settings';
        $this->route = 'admin.attendance-settings';
        $this->view = 'admin.attendance-settings';
        $this->path = 'attendance-settings';
        $this->access = 'attendance-setting';

        $this->middleware('permission:'.$this->access.'-view', ['only' => ['index']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['update', 'reset']]);
    }

    /**
     * Display the settings form.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Get all settings grouped by category
        $settings = AttendanceSetting::all();
        
        // If table is empty, seed default settings
        if ($settings->isEmpty()) {
            $this->seedDefaultSettings();
            $settings = AttendanceSetting::all();
        }
        
        // Convert to key-value array for easy access
        $data['settings'] = [];
        foreach ($settings as $setting) {
            $data['settings'][$setting->key] = [
                'value' => AttendanceSetting::getValue($setting->key),
                'type' => $setting->type,
                'description' => $setting->description,
                'category' => $setting->category,
            ];
        }

        // Group settings by category for display
        $data['categories'] = [
            'class_session' => [
                'title' => 'Class Session Settings',
                'icon' => 'fas fa-chalkboard-teacher',
                'description' => 'Configure how class sessions behave during kiosk mode',
            ],
            'scanning' => [
                'title' => 'QR Scanning Settings',
                'icon' => 'fas fa-qrcode',
                'description' => 'Configure student QR code scanning behavior',
            ],
            'logbook' => [
                'title' => 'Logbook Settings',
                'icon' => 'fas fa-book',
                'description' => 'Configure digital logbook requirements and features',
            ],
            'kiosk' => [
                'title' => 'Kiosk Display Settings',
                'icon' => 'fas fa-desktop',
                'description' => 'Configure kiosk interface display options',
            ],
        ];

        return view($this->view.'.index', $data);
    }

    /**
     * Update the settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        // Field Validation
        $request->validate([
            'minimum_class_duration_percentage' => 'required|integer|min:0|max:100',
            'late_threshold_percentage' => 'required|integer|min:0|max:100',
            'scan_cooldown_seconds' => 'required|integer|min:5|max:600',
            'kiosk_auto_refresh_seconds' => 'required|integer|min:10|max:300',
            'student_multi_scan_mode' => 'required|in:toggle,first_in',
        ]);

        try {
            DB::beginTransaction();

            // Update integer settings
            $integerSettings = [
                'minimum_class_duration_percentage',
                'late_threshold_percentage',
                'scan_cooldown_seconds',
                'kiosk_auto_refresh_seconds',
            ];

            foreach ($integerSettings as $key) {
                if ($request->has($key)) {
                    AttendanceSetting::setValue($key, $request->input($key));
                }
            }

            // Update boolean settings (checkboxes)
            $booleanSettings = [
                'auto_clock_out_enabled',
                'class_end_on_first_clock_out',
                'allow_extra_classes',
                'require_logbook_completion',
                'lecturer_attendance_auto_sync',
                'allow_class_rep_selection',
                'show_hod_on_logbook',
            ];

            foreach ($booleanSettings as $key) {
                $value = $request->has($key) ? '1' : '0';
                AttendanceSetting::setValue($key, $value);
            }

            // Update string settings
            if ($request->has('student_multi_scan_mode')) {
                AttendanceSetting::setValue('student_multi_scan_mode', $request->input('student_multi_scan_mode'));
            }

            DB::commit();

            // Log the change
            Log::info('Attendance settings updated', [
                'user_id' => Auth::guard('web')->id(),
                'user_name' => Auth::guard('web')->user()->name ?? 'Unknown',
            ]);

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update attendance settings', [
                'error' => $e->getMessage(),
                'user_id' => Auth::guard('web')->id(),
            ]);
            
            Flasher::addError('Failed to update settings: ' . $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Reset settings to defaults
     *
     * @return \Illuminate\Http\Response
     */
    public function reset()
    {
        try {
            DB::beginTransaction();
            
            // Delete all existing settings
            AttendanceSetting::truncate();
            
            // Re-seed default settings
            $this->seedDefaultSettings();
            
            DB::commit();

            Log::info('Attendance settings reset to defaults', [
                'user_id' => Auth::guard('web')->id(),
            ]);

            Flasher::addSuccess('Settings reset to defaults successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reset attendance settings', ['error' => $e->getMessage()]);
            Flasher::addError('Failed to reset settings: ' . $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Seed default settings
     */
    private function seedDefaultSettings()
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
        foreach ($settings as $setting) {
            AttendanceSetting::create(array_merge($setting, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
}
