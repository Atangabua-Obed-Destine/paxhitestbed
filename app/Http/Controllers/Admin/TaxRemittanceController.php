<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRemittance;
use App\Services\TaxRemittanceService;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Paying withheld tax over to the body it was withheld for.
 *
 * @see \App\Services\TaxRemittanceService for why the unit is the salary month
 */
class TaxRemittanceController extends Controller
{
    private $title = 'module_tax_remittance';
    private $route = 'admin.tax-remittance';
    private $view = 'admin.tax-remittance';
    private $path = 'tax-remittance';

    public function __construct()
    {
        $this->middleware('permission:tax-remittance-view', ['only' => ['index']]);
        $this->middleware('permission:tax-remittance-create', ['only' => ['store']]);
        $this->middleware('permission:tax-remittance-void', ['only' => ['void']]);
    }

    public function index(Request $request, TaxRemittanceService $service)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $rows = $service->outstanding();

        $data['rows'] = $rows;
        $data['reconciliation'] = $service->reconciliation();
        $data['source_accounts'] = $service->sourceAccounts();

        // The composition of each month, so the bursar can read the figures
        // straight onto a declaration form without a second request.
        $data['breakdowns'] = [];

        foreach ($rows as $row) {
            $data['breakdowns'][$row['account_id'] . '|' . $row['month']] =
                $service->breakdownFor($row['account_id'], $row['month']);
        }

        // The oldest month still owing anything. This is the one at risk of a
        // penalty, so it is called out rather than left to be spotted in a
        // table.
        $owing = array_values(array_filter($rows, fn ($r) => $r['outstanding'] > 0.005));
        $data['oldest_owing'] = $owing[0] ?? null;
        $data['total_owing'] = round(array_sum(array_column($owing, 'outstanding')), 2);

        $data['remittances'] = TaxRemittance::with(['liabilityAccount', 'sourceAccount', 'creator'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        return view($this->view . '.index', $data);
    }

    public function store(Request $request, TaxRemittanceService $service)
    {
        $request->validate([
            'liability_account_id' => 'required|exists:chart_of_accounts,id',
            'salary_month' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date|before_or_equal:today',
            'source_account_id' => 'required|exists:chart_of_accounts,id',
            'reference' => 'nullable|string|max:191',
            'note' => 'nullable|string|max:2000',
        ]);

        try {
            $remittance = $service->record([
                'liability_account_id' => $request->liability_account_id,
                'salary_month' => $request->salary_month,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'source_account_id' => $request->source_account_id,
                'reference' => $request->reference,
                'note' => $request->note,
                'allow_overpayment' => $request->boolean('allow_overpayment'),
                'allow_additional' => $request->boolean('allow_additional'),
            ], Auth::guard('web')->user()->id);

            Flasher::addSuccess(
                __('Recorded and posted') . ' — ' . $remittance->liabilityAccount->account_name,
                __('msg_success')
            );
        } catch (\RuntimeException $e) {
            // Expected refusals — a month already paid, more than is owed, a
            // source account that is not cash. The message names the reason,
            // so it is shown rather than swallowed.
            Flasher::addError($e->getMessage(), __('msg_error'));

            return redirect()->back()->withInput();
        } catch (\Exception $e) {
            Log::error('TaxRemittanceController::store — ' . $e->getMessage());
            Flasher::addError(__('msg_error_occurred') . ': ' . $e->getMessage(), __('msg_error'));

            return redirect()->back()->withInput();
        }

        return redirect()->route($this->route . '.index');
    }

    public function void(Request $request, $id, TaxRemittanceService $service)
    {
        $request->validate([
            'void_reason' => 'nullable|string|max:1000',
        ]);

        $remittance = TaxRemittance::findOrFail($id);

        try {
            $service->void($remittance, Auth::guard('web')->user()->id, $request->void_reason);

            Flasher::addSuccess(__('The payment was voided and reversed in the ledger.'), __('msg_success'));
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));
        } catch (\Exception $e) {
            Log::error('TaxRemittanceController::void — ' . $e->getMessage());
            Flasher::addError(__('msg_error_occurred') . ': ' . $e->getMessage(), __('msg_error'));
        }

        return redirect()->back();
    }
}
