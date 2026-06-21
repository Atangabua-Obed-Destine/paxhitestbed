<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ResultContribution;
use App\Models\ExamType;
use App\Models\ExamTypeContribution;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class CopyGlobalContributionsToCoursesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get the global result contribution (where subject_id is NULL)
        $globalContribution = ResultContribution::whereNull('subject_id')
            ->where('status', 1)
            ->first();

        if (!$globalContribution) {
            $this->command->error('No global result contribution found!');
            return;
        }

        // Get active exam types with their contributions
        $examTypes = ExamType::where('status', 1)->get();

        if ($examTypes->isEmpty()) {
            $this->command->error('No active exam types found!');
            return;
        }

        // Get all active subjects
        $subjects = Subject::where('status', 1)->get();

        if ($subjects->isEmpty()) {
            $this->command->warn('No active subjects found!');
            return;
        }

        $this->command->info("Found {$subjects->count()} active courses to configure...");
        $this->command->info("Global configuration:");
        $this->command->info("  - Attendance: {$globalContribution->attendances}%");
        $this->command->info("  - Assignments: {$globalContribution->assignments}%");
        $this->command->info("  - Activities: {$globalContribution->activities}%");
        foreach ($examTypes as $examType) {
            $this->command->info("  - {$examType->title}: {$examType->contribution}%");
        }
        $this->command->newLine();

        DB::beginTransaction();

        try {
            $createdCount = 0;
            $skippedCount = 0;

            foreach ($subjects as $subject) {
                // Check if subject already has configuration
                $existingConfig = ResultContribution::where('subject_id', $subject->id)
                    ->where('status', 1)
                    ->exists();

                if ($existingConfig) {
                    $this->command->warn("  Skipped: {$subject->code} - {$subject->title} (already configured)");
                    $skippedCount++;
                    continue;
                }

                // Create result contribution for this subject
                $subjectContribution = ResultContribution::create([
                    'subject_id' => $subject->id,
                    'attendances' => $globalContribution->attendances,
                    'assignments' => $globalContribution->assignments,
                    'activities' => $globalContribution->activities,
                    'status' => 1,
                ]);

                // Create exam type contributions for this subject
                foreach ($examTypes as $examType) {
                    ExamTypeContribution::create([
                        'subject_id' => $subject->id,
                        'exam_type_id' => $examType->id,
                        'contribution' => $examType->contribution,
                    ]);
                }

                $this->command->info("  ✓ Configured: {$subject->code} - {$subject->title}");
                $createdCount++;
            }

            DB::commit();

            $this->command->newLine();
            $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->command->info("✓ Successfully configured {$createdCount} courses");
            if ($skippedCount > 0) {
                $this->command->info("⊘ Skipped {$skippedCount} courses (already configured)");
            }
            $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Error: {$e->getMessage()}");
            throw $e;
        }
    }
}
