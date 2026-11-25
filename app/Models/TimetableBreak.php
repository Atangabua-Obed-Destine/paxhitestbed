<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A block of the week that is not a class: the midday break and Holy Mass,
 * Friday's club activities. Shown on every timetable on the days it applies
 * to — but only on days the class group has lessons, so a free day does not
 * show a lone "Break".
 */
class TimetableBreak extends Model
{
    use Auditable;

    protected $fillable = ['title', 'days', 'start_time', 'end_time', 'status'];

    protected $casts = ['days' => 'array', 'status' => 'boolean'];

    public function appliesTo(int $day): bool
    {
        return empty($this->days) || in_array($day, array_map('intval', $this->days), true);
    }

    /**
     * The breaks to show beside a set of classes, day by day: only on days
     * those classes meet.
     *
     * @return array<int, Collection> day number => breaks, as plain rows
     *         (is_break, title, start_time, end_time) a timetable can sort
     *         beside its classes
     */
    public static function forClasses(Collection $classes): array
    {
        $breaks = static::where('status', 1)->orderBy('start_time')->get();
        $days = $classes->pluck('day')->map(fn ($d) => (int) $d)->unique();
        $out = [];
        foreach ($days as $day) {
            $out[$day] = $breaks->filter(fn ($b) => $b->appliesTo($day))
                ->map(fn ($b) => (object) [
                    'id' => 'break-' . $b->id,
                    'is_break' => true,
                    'title' => $b->title,
                    'day' => (string) $day,
                    'start_time' => $b->start_time,
                    'end_time' => $b->end_time,
                ])->values();
        }

        return $out;
    }

    public function dayNames(): string
    {
        $names = [1 => 'Saturday', 2 => 'Sunday', 3 => 'Monday', 4 => 'Tuesday', 5 => 'Wednesday', 6 => 'Thursday', 7 => 'Friday'];

        return empty($this->days) ? __('Every day')
            : collect($this->days)->map(fn ($d) => __($names[(int) $d] ?? '?'))->implode(', ');
    }

    public function getAuditDescription($event)
    {
        return "Timetable break {$event}: {$this->title} {$this->start_time}-{$this->end_time}";
    }
}
