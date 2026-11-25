<?php

namespace App\Http\Middleware;

use Closure;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;

/**
 * Every action that posts money goes through the review dialog first.
 *
 * The dialog (admin.supplier-bill.partials.review-modal) shows what the action
 * would write, built by running it for real and rolling it back
 * (ReviewController::dryRun), and confirming adds reviewed=1. For the routes
 * listed here, a submission without it is refused, so the review cannot be
 * skipped by posting the form some other way.
 *
 * Screens with a review of their own (supplier bills and payments, bank
 * reconciliation, month close, journal reversal) enforce it in their
 * controllers and are not listed.
 */
class RequireReview
{
    public const ROUTES = [
        'admin.payroll.pay', 'admin.payroll.unpay', 'admin.payroll.bulk',
        'admin.tax-remittance.store', 'admin.tax-remittance.void',
        'admin.staff-loan.store', 'admin.staff-loan.cancel',
        'admin.payment-account-transfer.store', 'admin.payment-account.deposit.post', 'admin.payment-account.withdraw.post',
        'admin.recurring-entries.process', 'admin.recurring-entries.process-all',
        'admin.fixed-assets.post-depreciation', 'admin.fixed-assets.dispose',
        'admin.journal-entries.post', 'admin.journal-entries.unpost',
        'admin.student-credits.apply-to-fee', 'admin.student-credits.process-refund',
        'admin.year-end-closing.generate-entries', 'admin.year-end-closing.approve', 'admin.year-end-closing.reverse',
    ];

    public function handle(Request $request, Closure $next)
    {
        $name = optional($request->route())->getName();

        // Bulk payroll posts money only when it pays; generating and
        // approving post nothing and keep their simple confirm.
        $exempt = $name === 'admin.payroll.bulk' && $request->input('action') !== 'pay';

        if ($name && !$exempt && !$request->isMethod('GET') && in_array($name, self::ROUTES, true) && !$request->boolean('reviewed')) {
            $message = __('Review it first: open the review, check the details and tick the confirmation box.');

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            Flasher::addError($message, __('msg_error'));

            return redirect()->back()->withInput();
        }

        return $next($request);
    }
}
