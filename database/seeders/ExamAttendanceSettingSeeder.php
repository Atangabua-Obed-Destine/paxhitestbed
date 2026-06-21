<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ExamAttendanceSetting;

class ExamAttendanceSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default exam attendance eligibility settings
        ExamAttendanceSetting::create([
            'minimum_attendance_percentage' => 70.00,
            'is_enabled' => true,
            'updated_by' => 1, // Super Admin
        ]);
    }
}
