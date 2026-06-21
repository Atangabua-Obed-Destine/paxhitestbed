<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Web\Announcement;
use App\Models\Language;
use Carbon\Carbon;

class AnnouncementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get default language
        $defaultLanguage = Language::where('default', 1)->first();
        
        // Current active announcement (no end date)
        Announcement::create([
            'language_id' => $defaultLanguage?->id,
            'message' => '<strong>Welcome to PAX Higher Institute!</strong> Applications for the 2025/2026 Academic Year are now open.',
            'start_date' => Carbon::now()->subDays(7)->toDateString(),
            'end_date' => null,
            'status' => 1,
        ]);

        // Time-bound announcement (ends in 30 days)
        Announcement::create([
            'language_id' => $defaultLanguage?->id,
            'message' => '🎓 <em>Early Bird Discount</em>: Register before November 30th and save 15% on tuition fees!',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addDays(30)->toDateString(),
            'status' => 1,
        ]);

        // Future announcement (starts in 5 days)
        Announcement::create([
            'language_id' => $defaultLanguage?->id,
            'message' => '📚 E-Library Launch: Access thousands of digital books starting November 8th!',
            'start_date' => Carbon::now()->addDays(5)->toDateString(),
            'end_date' => Carbon::now()->addDays(60)->toDateString(),
            'status' => 1,
        ]);

        // Past announcement (should not display)
        Announcement::create([
            'language_id' => $defaultLanguage?->id,
            'message' => 'Semester exams completed. Results will be published soon.',
            'start_date' => Carbon::now()->subDays(30)->toDateString(),
            'end_date' => Carbon::now()->subDays(5)->toDateString(),
            'status' => 1,
        ]);

        // Inactive announcement (should not display)
        Announcement::create([
            'language_id' => $defaultLanguage?->id,
            'message' => 'This is a draft announcement for testing purposes.',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => null,
            'status' => 0,
        ]);

        // Global announcement (no language restriction)
        Announcement::create([
            'language_id' => null,
            'message' => '🌍 International Students: Special scholarship programs available. Contact admissions for details.',
            'start_date' => Carbon::now()->subDays(3)->toDateString(),
            'end_date' => Carbon::now()->addDays(90)->toDateString(),
            'status' => 1,
        ]);

        $this->command->info('Sample announcements created successfully!');
    }
}
