<?php

namespace App\Services\Assessment;

use App\Models\ProgramAssessmentConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AssessmentWeightService
{
    /**
     * Resolve the active assessment weight configuration for the given context.
     */
    public function resolve(int $programId, ?int $semesterId = null, ?int $subjectId = null, ?string $referenceDate = null): Collection
    {
        $date = $referenceDate ? Carbon::parse($referenceDate) : Carbon::today();

        $config = ProgramAssessmentConfig::query()
            ->where('program_id', $programId)
            ->when($semesterId, fn ($query) => $query->where('semester_id', $semesterId))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('subject_id')
            ->orderByDesc('semester_id')
            ->orderByDesc('effective_from')
            ->first();

        if ($config) {
            return collect([
                'exam_weight' => (float) $config->exam_weight,
                'ca_weight' => (float) $config->ca_weight,
                'attendance_weight' => (float) $config->attendance_weight,
            ]);
        }

        // Default fallback mirrors existing 60/40 split without attendance
        return collect([
            'exam_weight' => 60.0,
            'ca_weight' => 40.0,
            'attendance_weight' => 0.0,
        ]);
    }

    /**
     * Verify weights sum to 100.
     */
    public function isBalanced(Collection $weights): bool
    {
        return (int) round($weights->sum()) === 100;
    }
}
