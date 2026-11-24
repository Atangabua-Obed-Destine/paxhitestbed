<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Services\PayslipService;
use Illuminate\Support\Facades\Auth;

/**
 * A staff member's own payslips. Anyone signed in to the staff side can open
 * this page, and it only ever shows their own paid payrolls.
 */
class MyPayslipController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(PayrollSetting::current()->staff_payslips_enabled, 404);

            return $next($request);
        });
    }

    public function index()
    {
        $data['title'] = __('My Payslips');
        $data['rows'] = Payroll::where('user_id', Auth::guard('web')->id())
            ->where('status', 1)
            ->orderByDesc('salary_month')
            ->get();

        return view('admin.my-payslips.index', $data);
    }

    public function show($id)
    {
        $data = app(PayslipService::class)->data($this->own($id));
        $data['title'] = __('My Payslips');

        return view('admin.payroll.print', $data);
    }

    public function pdf($id)
    {
        $payroll = $this->own($id);
        $service = app(PayslipService::class);

        return response($service->pdf($payroll), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $service->filename($payroll) . '"',
        ]);
    }

    /** A paid payroll of the signed-in staff member, or not found. */
    protected function own($id): Payroll
    {
        return Payroll::with('user')
            ->where('user_id', Auth::guard('web')->id())
            ->where('status', 1)
            ->findOrFail($id);
    }
}
