<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\PayrollSetting;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The choices a school makes about how payroll runs: approval, carrying lines
 * over, part-month pay, payslips for staff and by email, the bank file
 * layout, and the accounts loans are booked to.
 */
class PayrollSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:payroll-setting');
    }

    public function index()
    {
        $data['title'] = __('Payroll Settings');
        $data['route'] = 'admin.payroll-setting';
        $data['settings'] = PayrollSetting::current();
        $data['accounts'] = ChartOfAccount::where('is_active', 1)->orderBy('account_code')->get(['id', 'account_code', 'account_name']);
        $data['columns'] = PayrollSetting::BANK_COLUMNS;
        $data['bases'] = PayrollSetting::PRORATION_BASES;

        return view('admin.payroll-setting.index', $data);
    }

    public function update(Request $request)
    {
        $request->validate([
            'require_approval' => 'required|boolean',
            'carry_over_lines' => 'required|boolean',
            'prorate_partial_months' => 'required|boolean',
            'proration_basis' => ['required', Rule::in(array_keys(PayrollSetting::PRORATION_BASES))],
            'staff_payslips_enabled' => 'required|boolean',
            'email_payslip_on_pay' => 'required|boolean',
            'bank_file_columns' => 'required|array|min:1',
            'bank_file_columns.*' => ['string', Rule::in(array_keys(PayrollSetting::BANK_COLUMNS))],
            'bank_file_delimiter' => ['required', Rule::in([',', ';', 'tab', '|'])],
            'bank_file_header' => 'required|boolean',
            'bank_file_reference' => 'required|string|max:120',
            'bank_file_decimals' => 'required|integer|between:0,2',
            'advance_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'advance_payment_account_id' => 'nullable|integer|exists:chart_of_accounts,id|different:advance_account_id',
        ], [
            'bank_file_columns.required' => __('Choose at least one column for the bank file.'),
            'advance_payment_account_id.different' => __('The account loans are paid from must differ from the staff advances account.'),
        ]);

        // The order the columns were ticked in is not the order wanted: the
        // page sends a position for each.
        $order = (array) $request->input('bank_file_order', []);
        $columns = collect($request->bank_file_columns)->unique()
            ->sortBy(fn ($c) => (int) ($order[$c] ?? 99))
            ->values()->all();

        $settings = PayrollSetting::current();
        $settings->fill([
            'require_approval' => $request->boolean('require_approval'),
            'carry_over_lines' => $request->boolean('carry_over_lines'),
            'prorate_partial_months' => $request->boolean('prorate_partial_months'),
            'proration_basis' => $request->proration_basis,
            'staff_payslips_enabled' => $request->boolean('staff_payslips_enabled'),
            'email_payslip_on_pay' => $request->boolean('email_payslip_on_pay'),
            'bank_file_columns' => $columns,
            'bank_file_delimiter' => $request->bank_file_delimiter,
            'bank_file_header' => $request->boolean('bank_file_header'),
            'bank_file_reference' => trim($request->bank_file_reference),
            'bank_file_decimals' => (int) $request->bank_file_decimals,
            'advance_account_id' => $request->advance_account_id ?: null,
            'advance_payment_account_id' => $request->advance_payment_account_id ?: null,
            'updated_by' => Auth::guard('web')->user()->id,
        ])->save();

        PayrollSetting::flush();

        Flasher::addSuccess(__('Payroll settings saved.'), __('msg_success'));

        return redirect()->route('admin.payroll-setting.index');
    }
}
