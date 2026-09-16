<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FeeCreditReconciliation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Fees → Credit audit: the fee credit audit, and the two corrections, one
 * click each.
 *
 * The same service the `fees:credit-audit` command uses, so the button and the
 * terminal can never disagree. Everything is worked out from the data on the
 * machine it runs on.
 */
class FeeCreditAuditController extends Controller
{
    protected FeeCreditReconciliation $audit;

    public function __construct(FeeCreditReconciliation $audit)
    {
        $this->audit = $audit;

        $this->middleware('permission:fee-credit-audit-view', ['only' => ['index']]);
        $this->middleware('permission:fee-credit-audit-correct', ['only' => ['voidDuplicates', 'correctLedger']]);
    }

    public function index()
    {
        $duplicates = $this->audit->duplicateCredits();
        $corrections = $this->audit->ledgerCorrections();

        return view('admin.fee-credit-audit.index', [
            'title' => __('Fee credit audit'),
            'collected' => $this->audit->collected(),
            'duplicates' => $duplicates,
            'corrections' => $corrections,
            'correctionsByMonth' => $corrections
                ->groupBy(fn ($row) => substr($row['entry_date'], 0, 7))
                ->map(fn ($rows, $month) => ['month' => $month, 'fees' => $rows->count(), 'amount' => $rows->sum('difference')])
                ->sortKeys()
                ->values(),
            'exposure' => $this->audit->ledgerExposure(),
            'unevidenced' => $this->audit->unevidencedPayments(),
            'orphans' => $this->audit->orphanApplications(),
        ]);
    }

    /** Cancel the unspent credit that no payment backs. */
    public function voidDuplicates()
    {
        $user = Auth::guard('web')->user();
        $result = $this->audit->voidDuplicateCredits($user->id, 'Credit audit page (' . $user->name . ')');

        Log::info('Fee credit audit: duplicated credit voided', $result + ['user_id' => $user->id]);

        return response()->json([
            'success' => true,
            'message' => $result['credits']
                ? __('Voided :amount FCFA across :count credit(s).', ['amount' => number_format($result['amount']), 'count' => $result['credits']])
                : __('There was no duplicated credit to void.'),
            'result' => $result,
        ]);
    }

    /** Repost every fee whose ledger posting differs from the cash it received. */
    public function correctLedger()
    {
        $user = Auth::guard('web')->user();
        $result = $this->audit->correctLedger($user->id);

        Log::info('Fee credit audit: ledger corrected', [
            'corrected' => $result['corrected'],
            'amount' => $result['amount'],
            'landed_today' => $result['landed_today'],
            'failed' => count($result['failed']),
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => $result['failed'] === [],
            'message' => $result['corrected']
                ? __('Reposted :count fee(s); fee postings reduced by :amount FCFA.', ['count' => $result['corrected'], 'amount' => number_format($result['amount'])])
                : __('Every fee posting already matches the cash received.'),
            'result' => $result,
        ]);
    }
}
