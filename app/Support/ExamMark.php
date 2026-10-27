<?php

namespace App\Support;

/**
 * One exam mark, bounded.
 *
 * The marking screen validated marks in the browser and nowhere else.
 * ExamMarkingController::store() accepted `marks` as an array with no rule on
 * the values and then assigned them straight through:
 *
 *     $exam->achieve_marks = $markValue;
 *
 * MySQL is not in strict mode on this installation, so 'abc' became 0.00 and
 * an over-long figure was clamped — silently, with the screen reporting
 * success. Nothing was corrupt when this was written (0 of 4,064 rows sat
 * outside their exam type's maximum), because the browser check had been
 * holding. But the only thing between a mistyped 755 and a wrong published
 * result was JavaScript.
 *
 * The maximum belongs to the exam type, not to the request — Final Exam is
 * marked out of 60 and CA out of 30 — so it is passed in rather than assumed.
 *
 * Nothing here writes. It answers two questions: is this acceptable, and what
 * value should be stored.
 */
class ExamMark
{
    /**
     * Is this what a marker could legitimately have entered?
     *
     * An empty box is legitimate: it means not marked yet, and the screen has
     * always saved that as null. Zero is legitimate and is NOT the same thing
     * — a student who sat the paper and scored nothing has a mark.
     *
     * @param  mixed  $value  as posted
     * @param  float|null  $max  the exam type's own maximum
     */
    public static function isAcceptable($value, ?float $max): bool
    {
        return self::refusal($value, $max) === null;
    }

    /**
     * Why this mark cannot be stored, or null if it can.
     *
     * The wording is what a marker sees, so it names the figure and the limit
     * rather than saying "invalid".
     *
     * @param  mixed  $value  as posted
     * @param  float|null  $max  the exam type's own maximum
     */
    public static function refusal($value, ?float $max): ?string
    {
        if (self::isBlank($value)) {
            return null;
        }

        if (!is_numeric($value)) {
            return __('exam_mark_not_a_number', ['value' => (string) $value]);
        }

        $mark = (float) $value;

        if ($mark < 0) {
            return __('exam_mark_negative', ['value' => self::tidy($mark)]);
        }

        /*
         * A missing maximum is not treated as "anything goes". An exam type
         * with no marks configured is a configuration fault, and accepting
         * unbounded marks against it is how a wrong result gets published.
         */
        if ($max === null || $max <= 0) {
            return __('exam_mark_no_maximum');
        }

        if ($mark > $max) {
            return __('exam_mark_above_maximum', [
                'value' => self::tidy($mark),
                'max' => self::tidy($max),
            ]);
        }

        return null;
    }

    /**
     * What to store: null for an empty box, otherwise a bounded float.
     *
     * Call this only on a value refusal() has already passed. It exists so no
     * raw request value reaches the column even once the checks are done —
     * the cast is in one place rather than at every assignment.
     *
     * @param  mixed  $value
     */
    public static function toStore($value): ?float
    {
        return self::isBlank($value) ? null : (float) $value;
    }

    /** An empty box, in any of the shapes a form can produce. */
    protected static function isBlank($value): bool
    {
        return $value === null || $value === '' || (is_string($value) && trim($value) === '');
    }

    /** 30 rather than 30.00, 17.5 rather than 17.50. */
    protected static function tidy(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
