<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Fee;
use App\Models\PaymentAccount;
use App\Models\PaymentReceipt;
use App\Models\Program;
use App\Models\Session as AcademicSession;
use App\Models\Setting;
use App\Models\Transaction;
use Carbon\Carbon;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admin-facing report and workflow for applicant admission fees.
 *
 * This is deliberately separate from the enrolled-student Fees Report at
 * `/admin/fees-student-report`, because admission fees have a different
 * lifecycle: they exist BEFORE the applicant becomes a student, they have no
 * matricule/session/semester, and they need to be settleable via a walk-in
 * cash payment recorded by the accountant.
 *
 * Scope: this controller only lists fees where `fees.applicant_id` is set.
 * Once the applicant is converted to a student, the fee still shows here
 * (as "Enrolled") so history is preserved, but the primary workflow is
 * about unsettled applicant admission fees.
 */
class AdmissionFeesReportController extends Controller
{
    protected string $title = 'Admission Fees Report';
    protected string $route = 'admin.admission-fees-report';
    protected string $view  = 'admin.admission-fees-report';

    /* -----------------------------------------------------------------
     |  List
     |----------------------------------------------------------------- */

    public function index(Request $request)
    {
        $query = Fee::query()
            ->whereNotNull('applicant_id')
            ->with([
                'applicant.program',
                'applicant.degreeType',
                'applicant.session',
                'category',
                'paymentReceipts.verifier',
                'studentEnroll.student',
            ])
            ->orderByDesc('created_at');

        // ---- Filters ----
        $filters = [
            'session'        => $request->input('session', ''),
            'program'        => $request->input('program', ''),
            'payment_status' => $request->input('payment_status', 'all'),
            'stage'          => $request->input('stage', 'all'),
            'search'         => trim((string) $request->input('search', '')),
            'from'           => $request->input('from', ''),
            'to'             => $request->input('to', ''),
        ];

        if ($filters['session'] !== '') {
            $query->whereHas('applicant', fn ($q) => $q->where('session_id', $filters['session']));
        }
        if ($filters['program'] !== '') {
            $query->whereHas('applicant', fn ($q) => $q->where('program_id', $filters['program']));
        }
        if ($filters['payment_status'] === 'paid') {
            $query->where('status', 1);
        } elseif ($filters['payment_status'] === 'partial') {
            $query->where('status', 2);
        } elseif ($filters['payment_status'] === 'unpaid') {
            $query->where(function ($q) {
                $q->where('status', 0)->orWhereNull('status');
            });
        }
        if ($filters['stage'] === 'applicant') {
            // Still an applicant: application not yet turned into a Student.
            $query->whereHas('applicant', fn ($q) => $q->where('status', '!=', 2));
        } elseif ($filters['stage'] === 'enrolled') {
            $query->whereHas('applicant', fn ($q) => $q->where('status', 2));
        }
        if ($filters['search'] !== '') {
            $needle = '%' . $filters['search'] . '%';
            $query->whereHas('applicant', function ($q) use ($needle) {
                $q->where('registration_no', 'like', $needle)
                    ->orWhere('first_name', 'like', $needle)
                    ->orWhere('last_name', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhere('phone', 'like', $needle);
            });
        }
        if ($filters['from'] !== '') {
            $query->whereDate('assign_date', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('assign_date', '<=', $filters['to']);
        }

        $fees = $query->paginate(25)->withQueryString();

        // Totals across the filtered (paginated) query.
        $totals = (clone $query)
            ->selectRaw('COUNT(*) as fee_count, COALESCE(SUM(fee_amount),0) as billed, COALESCE(SUM(paid_amount),0) as paid')
            ->first();

        return view($this->view . '.index', [
            'title'           => $this->title,
            'route'           => $this->route,
            'fees'            => $fees,
            'filters'         => $filters,
            'totals'          => $totals,
            'sessions'        => AcademicSession::orderBy('title')->get(),
            'programs'        => Program::orderBy('title')->get(),
            'paymentAccounts' => PaymentAccount::where('status', 1)->orderBy('title')->get(),
        ]);
    }

    /* -----------------------------------------------------------------
     |  Walk-in payment (cash / bank at the counter)
     |----------------------------------------------------------------- */

    /**
     * Record a manual counter payment against an applicant admission fee.
     * Idempotent per submission (no dedup key though — accountant is
     * responsible for not double-clicking). Produces an approved
     * PaymentReceipt and credits the Fee.
     */
    public function recordWalkIn(Request $request, Fee $fee)
    {
        abort_unless($fee->applicant_id, 404, 'This fee is not an applicant admission fee.');

        $validated = $request->validate([
            'amount'             => 'required|numeric|min:0.01',
            'payment_date'       => 'required|date|before_or_equal:today',
            'payment_method'     => 'required|in:2,3,4,8', // cash / cheque / bank / other
            'payment_reference'  => 'nullable|string|max:255',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'note'               => 'nullable|string|max:500',
        ]);

        $remaining = max(0, (float) $fee->fee_amount
            + (float) $fee->fine_amount
            - (float) $fee->discount_amount
            - (float) $fee->paid_amount);

        if ($remaining <= 0) {
            Flasher::addError(__('This admission fee is already fully paid.'));
            return back();
        }

        if ((float) $validated['amount'] > $remaining + 0.001) {
            Flasher::addError(__('Amount (:amt) exceeds the outstanding balance (:bal).', [
                'amt' => number_format((float) $validated['amount'], 2),
                'bal' => number_format($remaining, 2),
            ]));
            return back()->withInput();
        }

        try {
            DB::transaction(function () use ($fee, $validated) {
                $reference = $validated['payment_reference']
                    ?: 'WALKIN-' . strtoupper(Str::random(10));

                $receipt = new PaymentReceipt();
                $receipt->fee_id              = $fee->id;
                $receipt->applicant_id        = $fee->applicant_id;
                $receipt->student_id          = null;
                $receipt->receipt_file        = null;
                $receipt->payment_reference   = $reference;
                $receipt->payment_date        = $validated['payment_date'];
                $receipt->amount              = $validated['amount'];
                $receipt->payment_method      = (int) $validated['payment_method'];
                $receipt->payment_account_id  = $validated['payment_account_id'] ?? null;
                $receipt->student_note        = 'Walk-in payment recorded at counter.';
                $receipt->verification_status = 'approved';
                $receipt->verification_note   = $validated['note']
                    ?? 'Recorded by accountant on ' . now()->format('Y-m-d H:i');
                $receipt->verified_by         = Auth::guard('web')->id();
                $receipt->verified_at         = now();
                $receipt->save();

                $newPaid  = (float) $fee->paid_amount + (float) $validated['amount'];
                $totalDue = (float) $fee->fee_amount + (float) $fee->fine_amount - (float) $fee->discount_amount;

                if ($newPaid >= $totalDue) {
                    $status = 1;         // Fully Paid
                    $newPaid = $totalDue;
                } elseif ($newPaid > 0) {
                    $status = 2;         // Partial
                } else {
                    $status = 0;
                }

                $fee->paid_amount        = $newPaid;
                $fee->pay_date           = $receipt->payment_date;
                $fee->payment_method     = $receipt->payment_method;
                $fee->payment_account_id = $receipt->payment_account_id;
                $fee->status             = $status;
                $fee->note               = 'Walk-in payment recorded (ref ' . $reference . ')';
                $fee->save();

                // Bookkeeping. Applicant has no Student yet, so keep it standalone.
                $tx = new Transaction();
                $tx->transaction_id = Str::random(16);
                $tx->amount         = $validated['amount'];
                $tx->type           = '1';
                $tx->created_by     = Auth::guard('web')->id();
                $tx->save();

                session()->flash('walkin_receipt_id', $receipt->id);
            });
        } catch (\Throwable $e) {
            report($e);
            Flasher::addError(__('Failed to record payment: :m', ['m' => $e->getMessage()]));
            return back()->withInput();
        }

        Flasher::addSuccess(__('Payment recorded and receipt generated.'));

        // If the accountant likely wants to print, jump straight to the receipt.
        if ($request->boolean('print_after')) {
            return redirect()->route('admin.admission-fees-report.receipt', session('walkin_receipt_id'));
        }

        return redirect()->route('admin.admission-fees-report.index');
    }

    /* -----------------------------------------------------------------
     |  Digital receipt (printable)
     |----------------------------------------------------------------- */

    public function receipt(PaymentReceipt $receipt)
    {
        // Only receipts tied to applicant admission fees are served here.
        abort_unless($receipt->applicant_id || ($receipt->fee && $receipt->fee->applicant_id), 404);

        $receipt->load(['fee.category', 'fee.applicant.program', 'fee.applicant.degreeType',
            'fee.applicant.session', 'applicant.program', 'applicant.degreeType',
            'applicant.session', 'verifier', 'fee.paymentAccount']);

        $application = $receipt->applicant ?: optional($receipt->fee)->applicant;

        $setting = Setting::first();

        return view($this->view . '.receipt', [
            'receipt'          => $receipt,
            'application'      => $application,
            'setting'          => $setting,
            'verificationCode' => $this->verificationCodeFor($receipt),
        ]);
    }

    /* -----------------------------------------------------------------
     |  Public verification (no auth required)
     |----------------------------------------------------------------- */

    /**
     * Publicly verify a receipt. If a `code` query param is supplied and matches
     * the HMAC we computed at print time, show a green "verified" panel; otherwise
     * still show the core details but flagged as unverified.
     */
    public function publicVerify(Request $request, PaymentReceipt $receipt)
    {
        abort_unless($receipt->applicant_id || ($receipt->fee && $receipt->fee->applicant_id), 404);

        $receipt->load(['fee.category', 'fee.applicant.program', 'fee.applicant.degreeType',
            'fee.applicant.session', 'applicant.program', 'applicant.degreeType', 'applicant.session', 'verifier']);

        $expected  = $this->verificationCodeFor($receipt);
        $supplied  = strtoupper(trim((string) $request->query('code', '')));
        $isValid   = $supplied !== '' && hash_equals($expected, $supplied);

        $setting = Setting::first();
        $application = $receipt->applicant ?: optional($receipt->fee)->applicant;

        return view($this->view . '.public-verify', [
            'receipt'          => $receipt,
            'application'      => $application,
            'setting'          => $setting,
            'verificationCode' => $expected,
            'supplied'         => $supplied,
            'isValid'          => $isValid,
            'codeProvided'     => $supplied !== '',
        ]);
    }

    /**
     * Deterministic HMAC over the stable receipt attributes. Signed with the
     * application key so a printed code cannot be forged externally.
     */
    protected function verificationCodeFor(PaymentReceipt $receipt): string
    {
        $payload = implode('|', [
            $receipt->id,
            $receipt->fee_id,
            $receipt->applicant_id,
            $receipt->payment_reference,
            (string) $receipt->amount,
            optional($receipt->payment_date)->toDateString(),
        ]);
        return strtoupper(substr(
            hash_hmac('sha256', $payload, config('app.key')),
            0,
            16
        ));
    }
}
