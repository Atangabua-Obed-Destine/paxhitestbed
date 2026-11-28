<?php

namespace App\Services\Equipment;

use App\Models\Book;
use App\Models\Equipment\Item;
use App\Models\IdCardSetting;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Mailbox\Members;
use App\User;

/**
 * Turns whatever the desk scanned into a person, a piece of equipment or a book.
 *
 * What arrives depends on how it was scanned:
 *
 *   student card   barcode = the enrolment matricule; QR = .../verify-student/{matricule}
 *   staff card     barcode = users.staff_id;          QR = .../verify-staff/{users.id}
 *   equipment      sticker code, e.g. PRJ-003 (QR and Code 39 carry the same text)
 *   book           the ISBN on the library's label, or the book's own code
 *
 * A handheld scanner types the barcode followed by Enter; the camera hands
 * over the decoded text. Either way it ends up here. Someone typing what is
 * printed on a card may include the card's visible prefix, so that is tried
 * without it too.
 */
class ScanResolver
{
    public const EQUIPMENT = 'equipment';
    public const LIBRARY = 'library';

    /**
     * @return array{kind:string, member?:array, item?:Item, book?:Book}|null
     */
    public function resolve(?string $raw, string $desk = self::EQUIPMENT): ?array
    {
        $raw = trim((string) $raw);
        if ($raw === '' || mb_strlen($raw) > 300) {
            return null;
        }

        // QR codes on ID cards carry a verification link.
        if (preg_match('~/verify-staff/([^/?#\s]+)~i', $raw, $m)) {
            $staff = $this->staffByCard(urldecode($m[1]), true);

            return $staff ? ['kind' => 'person', 'member' => [Members::STAFF, $staff->id]] : null;
        }
        if (preg_match('~/verify-student/([^/?#\s]+)~i', $raw, $m)) {
            $studentId = $this->studentByCode(urldecode($m[1]));

            return $studentId ? ['kind' => 'person', 'member' => [Members::STUDENT, $studentId]] : null;
        }

        $code = strtoupper($raw);

        if ($desk === self::EQUIPMENT) {
            $item = Item::where('code', $code)->first();
            if ($item) {
                return ['kind' => 'item', 'item' => $item];
            }
        }

        foreach ($this->withoutPrefixes($code) as $candidate) {
            if ($staff = $this->staffByCard($candidate, false)) {
                return ['kind' => 'person', 'member' => [Members::STAFF, $staff->id]];
            }
            if ($studentId = $this->studentByCode($candidate)) {
                return ['kind' => 'person', 'member' => [Members::STUDENT, $studentId]];
            }
        }

        if ($desk === self::LIBRARY && ($book = $this->book($raw))) {
            return ['kind' => 'book', 'book' => $book];
        }

        return null;
    }

    /** The staff card's QR carries the user id; its barcode carries the staff id. */
    protected function staffByCard(string $value, bool $fromQr): ?User
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $query = User::query()->where('staff_id', $value);
        if ($fromQr && ctype_digit($value)) {
            $query->orWhere('id', (int) $value);
        }

        return $query->orderByRaw('staff_id = ? desc', [$value])->first();
    }

    protected function studentByCode(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $enrol = StudentEnroll::where('matricule', $value)->whereNotNull('student_id')->orderByDesc('id')->first();
        if ($enrol) {
            return (int) $enrol->student_id;
        }
        $student = Student::where('student_id', $value)->first();

        return $student ? (int) $student->id : null;
    }

    /** A book by its ISBN (with or without hyphens) or its own code. */
    public function book(string $raw): ?Book
    {
        $raw = trim($raw);
        $digits = preg_replace('/[^0-9Xx]/', '', $raw);

        return Book::query()
            ->where(function ($q) use ($raw, $digits) {
                $q->where('isbn', $raw)->orWhere('code', $raw);
                if ($digits !== '' && strlen($digits) >= 8) {
                    $q->orWhereRaw("REPLACE(REPLACE(isbn, '-', ''), ' ', '') = ?", [$digits]);
                }
            })
            ->orderByRaw('status = 1 desc')
            ->first();
    }

    /** The code as scanned, then without any ID card's printed prefix. */
    protected function withoutPrefixes(string $code): array
    {
        $out = [$code];
        $prefixes = IdCardSetting::whereIn('slug', ['student-card', 'staff-id-card'])->pluck('prefix')
            ->map(fn ($p) => strtoupper(trim((string) $p)))->filter()->unique();
        foreach ($prefixes as $prefix) {
            if (str_starts_with($code, $prefix) && strlen($code) > strlen($prefix)) {
                $out[] = substr($code, strlen($prefix));
            }
        }

        return array_values(array_unique($out));
    }
}
