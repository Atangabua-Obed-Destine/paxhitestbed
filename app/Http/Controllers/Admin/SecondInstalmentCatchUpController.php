<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Session;
use App\Services\SecondInstalmentCatchUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Fees → Second Instalment catch-up.
 *
 * Raises the Second Instalments that were never assigned, for students who were
 * gone before the second semester fees were configured. Nothing is written
 * until an admin ticks the students and confirms, and what it raises can be
 * taken back while untouched.
 */
class SecondInstalmentCatchUpController extends Controller
{
    protected SecondInstalmentCatchUp $catchUp;

    public function __construct(SecondInstalmentCatchUp $catchUp)
    {
        $this->catchUp = $catchUp;

        $this->middleware('permission:second-instalment-catchup-view', ['only' => ['index']]);
        $this->middleware('permission:second-instalment-catchup-apply', ['only' => ['apply', 'undo']]);
    }

    public function index(Request $request)
    {
        [$sessionId, $programId] = $this->filters($request);

        $rows = $this->catchUp->preview($sessionId, $programId ?: null);

        return view('admin.second-instalment-catchup.index', [
            'title' => __('Second Instalment catch-up'),
            'sessions' => Session::orderBy('title', 'desc')->get(),
            'programs' => Program::where('status', '1')->orderBy('title', 'asc')->get(),
            'selected_session' => $sessionId,
            'selected_program' => $programId,
            'rows' => $rows,
            'category' => $this->catchUp->category(),
            'totals' => [
                'students' => $rows->where('blocked', null)->count(),
                'raise' => $rows->where('blocked', null)->sum(fn ($row) => $row['amount'] + $row['fine']),
                'credit' => $rows->where('blocked', null)->sum('credit'),
                'owing' => $rows->where('blocked', null)->sum('owing'),
            ],
            'raised' => $this->catchUp->raised($sessionId, $programId ?: null),
        ]);
    }

    /** Raise the fee for the students ticked on the screen. */
    public function apply(Request $request)
    {
        $request->validate([
            'session_id' => ['required', 'exists:sessions,id'],
            'students' => ['required', 'array'],
        ]);

        $user = Auth::guard('web')->user();
        $result = $this->catchUp->apply($request->input('students', []), (int) $request->input('session_id'), $user->id);

        Log::info('Second Instalment catch-up applied', $result + ['user_id' => $user->id, 'session_id' => (int) $request->input('session_id')]);

        return response()->json([
            'success' => $result['failed'] === [],
            'message' => $result['billed']
                ? __(':count fee(s) raised, :credit FCFA settled by credit, :owing FCFA left owing.', [
                    'count' => $result['billed'],
                    'credit' => number_format($result['credit_applied']),
                    'owing' => number_format($result['owing']),
                ])
                : __('Nothing was raised.'),
            'result' => $result,
        ]);
    }

    /** Take back fees the catch-up raised, while nothing has been put against them. */
    public function undo(Request $request)
    {
        $request->validate(['fees' => ['required', 'array']]);

        $user = Auth::guard('web')->user();
        $result = $this->catchUp->undo($request->input('fees', []), $user->id);

        Log::info('Second Instalment catch-up undone', $result + ['user_id' => $user->id]);

        return response()->json([
            'success' => $result['refused'] === [],
            'message' => $result['removed']
                ? __(':count fee(s) removed, :amount FCFA taken off what students owe.', [
                    'count' => $result['removed'], 'amount' => number_format($result['amount']),
                ])
                : __('Nothing was removed.'),
            'result' => $result,
        ]);
    }

    /** @return array{0:int,1:int} session, programme (0 = all) */
    protected function filters(Request $request): array
    {
        $sessionId = (int) ($request->input('session_id')
            ?: optional(Session::where('status', '1')->orderBy('id', 'desc')->first())->id
            ?: optional(Session::orderBy('id', 'desc')->first())->id);

        return [$sessionId, (int) $request->input('program_id', 0)];
    }
}
