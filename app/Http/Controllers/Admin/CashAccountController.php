<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\LedgerCashSetting;
use App\Models\PaymentAccount;
use App\Services\Accounting\CashAccounts;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Where money is posted in the ledger: the default account for each kind of
 * payment method, and the ledger account behind each cash box, bank account
 * and mobile-money wallet. See App\Services\Accounting\CashAccounts.
 */
class CashAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:transaction-mapping-settings');
    }

    public function index(CashAccounts $cash)
    {
        return view('admin.accounting.cash-accounts.index', [
            'title' => __('Cash and bank in the ledger'),
            'setting' => LedgerCashSetting::current(),
            'ledgerAccounts' => $this->cashLedgerAccounts(),
            'paymentAccounts' => PaymentAccount::orderBy('title')->get(),
            'comparison' => $cash->comparison(),
        ]);
    }

    public function update(Request $request)
    {
        $ids = $this->cashLedgerAccounts()->pluck('id')->all();
        $rule = ['nullable', 'integer', \Illuminate\Validation\Rule::in($ids)];

        $validated = $request->validate([
            'cash_account_id' => $rule,
            'bank_account_id' => $rule,
            'mobile_money_account_id' => $rule,
            'other_account_id' => $rule,
            'links' => ['nullable', 'array'],
            'links.*' => $rule,
        ], [
            'in' => __('Choose a cash, bank or mobile-money account (class 5).'),
        ]);

        LedgerCashSetting::current()->fill([
            'cash_account_id' => $validated['cash_account_id'] ?? null,
            'bank_account_id' => $validated['bank_account_id'] ?? null,
            'mobile_money_account_id' => $validated['mobile_money_account_id'] ?? null,
            'other_account_id' => $validated['other_account_id'] ?? null,
            'updated_by' => Auth::id(),
        ])->save();

        foreach ($validated['links'] ?? [] as $paymentAccountId => $ledgerAccountId) {
            PaymentAccount::whereKey($paymentAccountId)->update(['chart_of_account_id' => $ledgerAccountId ?: null]);
        }

        Flasher::addSuccess(__('Saved. New receipts, expenses, payroll and transfers will post to these accounts; what is already posted stays where it is.'), __('msg_success'));

        return redirect()->route('admin.accounting.cash-accounts');
    }

    /** Class-5 detail accounts, less 59 (provisions on treasury). */
    private function cashLedgerAccounts()
    {
        return ChartOfAccount::where('account_code', 'like', '5%')
            ->where('account_code', 'not like', '59%')
            ->where('account_category', 'detail')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);
    }
}
