<?php

namespace App\Services\Mailbox;

use App\Models\Student;
use App\User;

/**
 * Who a mailbox member is.
 *
 * A member is a pair [type, id]: ['staff', users.id] or ['student',
 * students.id]. Staff and students live in different tables and log in on
 * different guards, so every conversation names its people this way.
 */
class Members
{
    public const STAFF = 'staff';
    public const STUDENT = 'student';

    /** @var array<string, object|null> */
    protected array $cache = [];

    /** The member for whoever is logged in on the guard handling this request. */
    public static function current(): ?array
    {
        if ($u = auth('web')->user()) {
            return [self::STAFF, (int) $u->id];
        }
        if ($s = auth('student')->user()) {
            return [self::STUDENT, (int) $s->id];
        }

        return null;
    }

    public static function key(array $m): string
    {
        return $m[0] . ':' . $m[1];
    }

    public static function same(array $a, array $b): bool
    {
        return $a[0] === $b[0] && (int) $a[1] === (int) $b[1];
    }

    /** Load several members in two queries. */
    public function load(array $members): void
    {
        $want = [self::STAFF => [], self::STUDENT => []];
        foreach ($members as $m) {
            if (!array_key_exists(self::key($m), $this->cache) && isset($want[$m[0]])) {
                $want[$m[0]][] = (int) $m[1];
            }
        }
        if ($want[self::STAFF]) {
            $rows = User::whereIn('id', array_unique($want[self::STAFF]))
                ->with(['designation:id,title', 'department:id,title'])
                ->get(['id', 'staff_id', 'first_name', 'last_name', 'email', 'status', 'photo', 'designation_id', 'department_id'])->keyBy('id');
            foreach ($want[self::STAFF] as $id) {
                $this->cache[self::STAFF . ':' . $id] = $rows->get($id);
            }
        }
        if ($want[self::STUDENT]) {
            $rows = Student::whereIn('id', array_unique($want[self::STUDENT]))
                ->get(['id', 'student_id', 'first_name', 'last_name', 'email', 'status', 'photo'])->keyBy('id');
            foreach ($want[self::STUDENT] as $id) {
                $this->cache[self::STUDENT . ':' . $id] = $rows->get($id);
            }
        }
    }

    public function record(array $m): ?object
    {
        if (!array_key_exists(self::key($m), $this->cache)) {
            $this->load([$m]);
        }

        return $this->cache[self::key($m)] ?? null;
    }

    public function name(array $m): string
    {
        $r = $this->record($m);
        if (!$r) {
            return __('Former member');
        }

        return trim($r->first_name . ' ' . $r->last_name);
    }

    /** A short line under the name: staff designation, or the student's matricule. */
    public function detail(array $m): string
    {
        $r = $this->record($m);
        if (!$r) {
            return '';
        }
        if ($m[0] === self::STUDENT) {
            return (string) $r->student_id;
        }

        return (string) (optional($r->designation)->title ?: optional($r->department)->title ?: __('Staff'));
    }

    public function email(array $m): ?string
    {
        $r = $this->record($m);
        $email = $r ? trim((string) $r->email) : '';

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function active(array $m): bool
    {
        $r = $this->record($m);

        return $r && (string) $r->status === '1';
    }
}
