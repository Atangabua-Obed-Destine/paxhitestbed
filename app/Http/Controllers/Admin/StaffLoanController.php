<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffLoan;
use App\Services\StaffLoanAccounting;
use App\User;
use Carbon\Carbon;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Loans and salary advances to staff, repaid by a deduction on each payroll.
 */
class StaffLoanController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:staff-loan-view', ['only' => ['index']]);
        $this->middleware('permission:staff-loan-action', ['only' => ['store', 'cancel']]);
    }

    public function index(Request $request)
    {
        $data['title'] = __('Staff Loans and Advances');
        $data['route'] = 'admin.staff-loan';
        $data['selected_status'] = $request->get('status', 'open');

        $loans = StaffLoan::with('user')->orderByDesc('id')->get();

        $data['rows'] = $loans->map(function ($loan) {
            $loan->repaid_amount = $loan->repaid();
            $loan->pending_amount = $loan->pending();
            $loan->balance_amount = round(max(0, $loan->amount - $loan->repaid_amount), 2);
            $loan->state = $loan->status === 'cancelled' ? 'cancelled' : ($loan->balance_amount <= 0.004 ? 'repaid' : 'open');
            $loan->has_lines = $loan->lines()->exists();
            return $loan;
        });

        $data['counts'] = $data['rows']->countBy('state');
        $data['rows'] = $data['rows']
            ->filter(fn ($loan) => $data['selected_status'] === 'all' || $loan->state === $data['selected_status'])
            ->values();

        $data['staff'] = User::where('status', '1')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Admin']))
            ->whereNotNull('salary_type')
            ->orderBy('first_name')->get(['id', 'staff_id', 'first_name', 'last_name']);
        $data['kinds'] = StaffLoan::KINDS;
        $data['accounting'] = app(StaffLoanAccounting::class)->configured();

        return view('admin.staff-loan.index', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 1)],
            'kind' => ['required', Rule::in(array_keys(StaffLoan::KINDS))],
            'title' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:1',
            'instalment' => 'required|numeric|min:1|lte:amount',
            'start_month' => 'required|date_format:Y-m',
            'given_on' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:500',
        ], [
            'instalment.lte' => __('The monthly repayment cannot be more than the amount lent.'),
        ]);

        $by = Auth::guard('web')->user()->id;
        $title = trim((string) $request->title) ?: StaffLoan::KINDS[$request->kind];

        try {
            DB::transaction(function () use ($request, $by, $title) {
                $loan = StaffLoan::create([
                    'user_id' => $request->user_id,
                    'kind' => $request->kind,
                    'title' => $title,
                    'amount' => round((float) $request->amount, 2),
                    'instalment' => round((float) $request->instalment, 2),
                    'start_month' => Carbon::createFromFormat('Y-m', $request->start_month)->startOfMonth()->format('Y-m-d'),
                    'given_on' => $request->given_on,
                    'status' => 'active',
                    'note' => $request->note,
                    'created_by' => $by,
                ]);

                // Recorded in the accounts in the same transaction, so a loan
                // never exists in payroll without its entry when the school
                // has asked for one.
                app(StaffLoanAccounting::class)->recordGiven($loan->load('user'), $by);
            });
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));

            return redirect()->back()->withInput();
        }

        Flasher::addSuccess(__('The loan was recorded. Its repayment will be added to each payroll from :month.', [
            'month' => Carbon::createFromFormat('Y-m', $request->start_month)->format('F Y'),
        ]), __('msg_success'));

        return redirect()->route('admin.staff-loan.index');
    }

    /**
     * Cancel a loan entered by mistake. Only before anything has been taken
     * from a payroll for it: a loan partly repaid is a real debt, not a
     * mistake, and its history must stay.
     */
    public function cancel($id)
    {
        $loan = StaffLoan::with('user')->findOrFail($id);

        if ($loan->status !== 'active') {
            Flasher::addWarning(__('This loan is already cancelled.'), __('msg_warning'));

            return redirect()->back();
        }

        if ($loan->lines()->exists()) {
            Flasher::addError(__('Repayments for this loan are already on a payroll, so it cannot be cancelled. Remove the repayment from any unpaid payroll first; a loan already partly repaid stays on record.'), __('msg_error'));

            return redirect()->back();
        }

        try {
            DB::transaction(function () use ($loan) {
                $by = Auth::guard('web')->user()->id;
                app(StaffLoanAccounting::class)->reverseGiven($loan, $by);
                $loan->forceFill(['status' => 'cancelled', 'updated_by' => $by])->save();
            });
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));

            return redirect()->back();
        }

        Flasher::addSuccess(__('The loan was cancelled.'), __('msg_success'));

        return redirect()->back();
    }
}
