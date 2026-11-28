<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\IssueReturn;
use App\Services\Equipment\DeskRefusal;
use App\Services\Equipment\ScanResolver;
use App\Services\Library\ScanDesk;
use App\Services\Mailbox\Members;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The library's scan desk, beside the existing Issue and Return pages. It uses
 * the library's own permission (book-issue-action) and writes the same rows.
 */
class LibraryScanDeskController extends Controller
{
    public function __construct(protected ScanDesk $desk, protected ScanResolver $resolver)
    {
        $this->middleware('permission:book-issue-action');
    }

    public function desk()
    {
        return view('admin.library-scan-desk.index', [
            'title' => __('Library scan desk'),
            'dueDate' => $this->desk->defaultDueDate()->format('Y-m-d'),
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:300']);
        $hit = $this->resolver->resolve($request->input('code'), ScanResolver::LIBRARY);

        if (!$hit || !in_array($hit['kind'], ['person', 'book'], true)) {
            return response()->json(['ok' => false, 'message' => __('Nothing matches ":code". Check the card or the book\'s label and scan again.', ['code' => mb_substr(trim($request->input('code')), 0, 60)])], 404);
        }
        if ($hit['kind'] === 'person') {
            return response()->json(['ok' => true, 'kind' => 'person', 'person' => $this->desk->person($hit['member'])]);
        }

        return response()->json(['ok' => true, 'kind' => 'book', 'book' => $this->desk->book($hit['book'])]);
    }

    public function issue(Request $request): JsonResponse
    {
        $request->validate([
            'person' => 'required|string|max:40',
            'book_id' => 'required|integer',
            'due_date' => 'required|date',
        ]);
        $person = $this->member($request->input('person'));
        $book = Book::find($request->input('book_id'));
        if (!$person || !$book) {
            return response()->json(['ok' => false, 'message' => __('Scan the card and the book again.')], 422);
        }

        try {
            $result = $this->desk->issue($person, $book, $request->input('due_date'), Auth::guard('web')->id());
        } catch (DeskRefusal $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $name = app(Members::class)->name($person);
        $message = __(':title issued to :name, due back :due.', ['title' => $book->title, 'name' => $name, 'due' => \Carbon\Carbon::parse($result['issue']->due_date)->format('d M')]);
        if ($result['joined']) {
            $message .= ' ' . __(':name is now a library member.', ['name' => $name]);
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'joined' => $result['joined'],
            'person' => $this->desk->person($person),
            'book' => $this->desk->book($book->fresh()),
        ]);
    }

    public function returnBook(Request $request): JsonResponse
    {
        $request->validate(['issue_id' => 'required|integer']);
        $issue = IssueReturn::with('book')->find($request->input('issue_id'));
        if (!$issue) {
            return response()->json(['ok' => false, 'message' => __('Scan the book again.')], 422);
        }

        try {
            $this->desk->returnIssue($issue, Auth::guard('web')->id());
        } catch (DeskRefusal $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => __(':title returned. It is back on the shelf.', ['title' => optional($issue->book)->title]),
            'book' => $issue->book ? $this->desk->book($issue->book->fresh()) : null,
        ]);
    }

    protected function member(?string $key): ?array
    {
        if (!$key || !preg_match('/^(staff|student):(\d+)$/', $key, $m)) {
            return null;
        }
        $member = [$m[1], (int) $m[2]];

        return app(Members::class)->record($member) ? $member : null;
    }
}
