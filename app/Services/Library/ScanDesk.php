<?php

namespace App\Services\Library;

use App\Models\Book;
use App\Models\IdCardSetting;
use App\Models\IssueReturn;
use App\Models\LibraryMember;
use App\Models\Student;
use App\Services\Equipment\DeskRefusal;
use App\Services\Equipment\LoanDesk;
use App\Services\Mailbox\Members;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The library's scan desk: scan a card and a book to issue it, scan a book to
 * take it back. It writes the same issue_returns rows as the existing Issue
 * and Return pages, so everything the library already shows keeps working.
 *
 * Two differences from the pages that pick from lists:
 *   - a student or member of staff who is not yet a library member becomes
 *     one on their first issue; their ID card already proves who they are,
 *     and their library ID is their own staff ID or matricule
 *   - the due date defaults to the library loan days in Desk settings
 *
 * A book's barcode is its ISBN, the same on every copy, so the desk knows the
 * title and not the copy. When more than one person has that title out, the
 * desk asks which of them is returning it.
 */
class ScanDesk
{
    public function __construct(protected Members $members, protected LoanDesk $desk)
    {
    }

    public function person(array $m): array
    {
        $member = $this->memberFor($m);
        $issues = $member
            ? IssueReturn::with('book:id,title')->where('member_id', $member->id)->where('status', 1)->orderBy('due_date')->get()
            : collect();

        $blockers = $this->desk->blockers($m, true);
        if ($member && (string) $member->status !== '1') {
            $blockers[] = __('Their library membership is switched off. Switch it back on under Library members first.');
        }

        return $this->desk->describe($m) + [
            'library_id' => optional($member)->library_id,
            'is_member' => (bool) $member,
            'holding' => $issues->map(fn (IssueReturn $i) => [
                'issue_id' => $i->id,
                'book_id' => $i->book_id,
                'title' => optional($i->book)->title,
                'due' => Carbon::parse($i->due_date)->format('d M'),
                'overdue' => Carbon::parse($i->due_date)->lt(today()),
            ])->values()->all(),
            'blockers' => $blockers,
        ];
    }

    public function book(Book $book): array
    {
        $open = $this->openIssues($book);

        return [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn,
            'shelf' => trim(implode(', ', array_filter([
                $book->section ? __('section :v', ['v' => $book->section]) : null,
                $book->column ? __('column :v', ['v' => $book->column]) : null,
                $book->row ? __('row :v', ['v' => $book->row]) : null,
            ]))),
            'quantity' => (int) $book->quantity,
            'on_shelf' => max(0, (int) $book->quantity - $open->count()),
            'available' => (string) $book->status === '1',
            'holders' => $open->map(function (IssueReturn $i) {
                $person = $this->personOfMember($i->member);

                return [
                    'issue_id' => $i->id,
                    'name' => $person ? $this->members->name($person) : (optional($i->member)->library_id ?? __('Unknown member')),
                    'person_key' => $person ? Members::key($person) : null,
                    'due' => Carbon::parse($i->due_date)->format('d M'),
                    'overdue' => Carbon::parse($i->due_date)->lt(today()),
                ];
            })->values()->all(),
        ];
    }

    public function openIssues(Book $book)
    {
        return IssueReturn::with('member.memberable')->where('book_id', $book->id)->where('status', 1)->orderBy('due_date')->get();
    }

    public function defaultDueDate(): Carbon
    {
        return today()->addDays(max(1, (int) $this->desk->settings()->library_loan_days));
    }

    /**
     * Issue a copy of the book.
     *
     * @return array{issue:IssueReturn, joined:bool}
     */
    public function issue(array $m, Book $book, $dueDate, int $by): array
    {
        $due = $dueDate instanceof Carbon ? $dueDate->copy()->startOfDay() : Carbon::parse($dueDate)->startOfDay();
        if ($due->lt(today())) {
            throw new DeskRefusal(__('The due date cannot be in the past.'));
        }
        if ($blockers = $this->person($m)['blockers']) {
            throw new DeskRefusal($blockers[0]);
        }

        return DB::transaction(function () use ($m, $book, $due, $by) {
            $book = Book::whereKey($book->id)->lockForUpdate()->first();
            if (!$book || (string) $book->status !== '1') {
                throw new DeskRefusal(__('This book is not available for lending.'));
            }
            $out = IssueReturn::where('book_id', $book->id)->where('status', 1)->count();
            if ($out >= (int) $book->quantity) {
                throw new DeskRefusal(trans_choice('The only copy is out.|All :count copies are out.', (int) $book->quantity, ['count' => (int) $book->quantity]));
            }

            $member = $this->memberFor($m);
            $joined = false;
            if (!$member) {
                $member = $this->join($m, $by);
                $joined = true;
            }

            $issue = new IssueReturn();
            $issue->book_id = $book->id;
            $issue->member_id = $member->id;
            $issue->issue_date = today()->format('Y-m-d');
            $issue->due_date = $due->format('Y-m-d');
            $issue->issued_by = $by;
            $issue->status = 1;
            $issue->save();

            return ['issue' => $issue, 'joined' => $joined];
        });
    }

    public function returnIssue(IssueReturn $issue, int $by): IssueReturn
    {
        return DB::transaction(function () use ($issue, $by) {
            $issue = IssueReturn::whereKey($issue->id)->lockForUpdate()->first();
            if (!$issue || (int) $issue->status !== 1) {
                throw new DeskRefusal(__('That book is not out, so there is nothing to return.'));
            }
            $issue->return_date = today()->format('Y-m-d');
            $issue->status = 2;
            $issue->received_by = $by;
            $issue->save();

            return $issue;
        });
    }

    /** The person's library membership, if they have one. */
    public function memberFor(array $m): ?LibraryMember
    {
        $model = $this->model($m);

        return $model ? $model->member()->first() : null;
    }

    /** Make the person a library member, with their own ID as their library ID. */
    protected function join(array $m, int $by): LibraryMember
    {
        $model = $this->model($m);
        if (!$model) {
            throw new DeskRefusal(__('That card does not belong to anyone the library can lend to.'));
        }
        $described = $this->desk->describe($m);
        $base = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $described['code']) ?: strtoupper($m[0][0]) . $m[1];
        $libraryId = $base;
        for ($n = 2; LibraryMember::where('library_id', $libraryId)->exists(); $n++) {
            $libraryId = $base . '-' . $n;
        }

        $card = IdCardSetting::where('slug', 'library-card')->first();
        $member = new LibraryMember();
        $member->library_id = $libraryId;
        $member->date = Carbon::today()->addYears((int) ($card->validity ?? 0))->format('Y-m-d');
        $member->status = '1';
        $member->created_by = $by;
        $model->member()->save($member);

        return $member;
    }

    protected function model(array $m)
    {
        return $m[0] === Members::STUDENT ? Student::find($m[1]) : User::find($m[1]);
    }

    /** The [type, id] person behind a library membership; null for outside members. */
    public function personOfMember(?LibraryMember $member): ?array
    {
        $owner = $member?->memberable;
        if ($owner instanceof Student) {
            return [Members::STUDENT, (int) $owner->id];
        }
        if ($owner instanceof User) {
            return [Members::STAFF, (int) $owner->id];
        }

        return null;
    }
}
