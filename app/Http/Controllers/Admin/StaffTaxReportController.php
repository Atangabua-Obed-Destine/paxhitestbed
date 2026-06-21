<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use App\Models\TaxGroup;
use App\Models\TaxSetting;
use App\Models\StaffTaxExemption;
use App\Models\Payroll;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StaffTaxReportExport;
use Carbon\Carbon;

class StaffTaxReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:tax-setting-view|payroll-report');
    }

    /**
     * Staff Tax Distribution Report
     * Shows per-staff tax breakdown for all active tax groups and standalone taxes.
     */
    public function index(Request $request)
    {
        $data['title'] = 'Staff Tax Distribution Report';

        // Filters
        $data['departments'] = Department::where('status', '1')->orderBy('title')->get();
        $data['designations'] = Designation::where('status', '1')->orderBy('title')->get();
        $data['selected_department'] = $request->department_id;
        $data['selected_designation'] = $request->designation_id;
        $data['selected_salary_min'] = $request->salary_min;
        $data['selected_salary_max'] = $request->salary_max;

        // Effective date — defaults to today
        $effectiveDate = $request->effective_date ? Carbon::parse($request->effective_date) : Carbon::today();
        $data['effective_date'] = $effectiveDate->format('Y-m-d');

        // Get effective tax groups with brackets
        $taxGroups = TaxGroup::active()
            ->effectiveOn($effectiveDate)
            ->ordered()
            ->with(['brackets' => function ($q) use ($effectiveDate) {
                $q->where('status', 1)
                  ->where(function ($q2) use ($effectiveDate) {
                      $q2->whereNull('effective_from')->orWhere('effective_from', '<=', $effectiveDate);
                  })
                  ->where(function ($q2) use ($effectiveDate) {
                      $q2->whereNull('effective_to')->orWhere('effective_to', '>=', $effectiveDate);
                  })
                  ->orderBy('bracket_order')->orderBy('min_amount');
            }])
            ->get();

        // Get effective standalone tax brackets
        $standaloneTaxes = TaxSetting::active()
            ->standalone()
            ->effectiveOn($effectiveDate)
            ->ordered()
            ->get();

        $data['tax_groups'] = $taxGroups;
        $data['standalone_taxes'] = $standaloneTaxes;

        // Build column headers: each tax group + each standalone tax
        $taxColumns = [];
        foreach ($taxGroups as $group) {
            $taxColumns[] = [
                'type'  => 'group',
                'id'    => $group->id,
                'title' => $group->title,
                'code'  => $group->code,
            ];
        }
        foreach ($standaloneTaxes as $tax) {
            $taxColumns[] = [
                'type'  => 'standalone',
                'id'    => $tax->id,
                'title' => $tax->title,
            ];
        }
        $data['tax_columns'] = $taxColumns;

        // Active staff query
        $staffQuery = User::where('status', '1')
            ->whereNotNull('basic_salary')
            ->where('basic_salary', '>', 0)
            ->with(['exemptTaxes']);

        if ($request->department_id) {
            $staffQuery->where('department_id', $request->department_id);
        }
        if ($request->designation_id) {
            $staffQuery->where('designation_id', $request->designation_id);
        }
        if ($request->salary_min) {
            $staffQuery->where('basic_salary', '>=', $request->salary_min);
        }
        if ($request->salary_max) {
            $staffQuery->where('basic_salary', '<=', $request->salary_max);
        }

        $staff = $staffQuery->orderBy('basic_salary', 'desc')->get();

        // Load exemptions for these staff
        $staffIds = $staff->pluck('id')->toArray();
        $allExemptions = StaffTaxExemption::whereIn('user_id', $staffIds)
            ->notExpired($effectiveDate)
            ->get()
            ->groupBy('user_id');

        // Calculate tax distribution for each staff member
        $staffRows = [];
        $grandTotals = [
            'basic_salary' => 0,
            'employee_tax_total' => 0,
            'employer_tax_total' => 0,
            'net_salary' => 0,
            'total_cost' => 0,
            'groups' => [],
            'standalone' => [],
        ];

        // Init grand totals per column
        foreach ($taxGroups as $g) {
            $grandTotals['groups'][$g->id] = ['employee' => 0, 'employer' => 0];
        }
        foreach ($standaloneTaxes as $s) {
            $grandTotals['standalone'][$s->id] = ['employee' => 0, 'employer' => 0];
        }

        foreach ($staff as $user) {
            $userExemptions = isset($allExemptions[$user->id])
                ? $allExemptions[$user->id]->keyBy('tax_setting_id')
                : collect();

            $row = $this->calculateStaffTaxes($user, $taxGroups, $standaloneTaxes, $userExemptions);

            // Accumulate grand totals
            foreach ($taxGroups as $group) {
                if (isset($row['groups'][$group->id])) {
                    $grandTotals['groups'][$group->id]['employee'] += $row['groups'][$group->id]['employee'];
                    $grandTotals['groups'][$group->id]['employer'] += $row['groups'][$group->id]['employer'];
                }
            }
            foreach ($standaloneTaxes as $tax) {
                if (isset($row['standalone'][$tax->id])) {
                    $grandTotals['standalone'][$tax->id]['employee'] += $row['standalone'][$tax->id]['employee'];
                    $grandTotals['standalone'][$tax->id]['employer'] += $row['standalone'][$tax->id]['employer'];
                }
            }

            $grandTotals['basic_salary'] += $row['basic_salary'];
            $grandTotals['employee_tax_total'] += $row['employee_tax_total'];
            $grandTotals['employer_tax_total'] += $row['employer_tax_total'];
            $grandTotals['net_salary'] += $row['net_salary'];
            $grandTotals['total_cost'] += $row['total_cost'];

            $staffRows[] = $row;
        }

        $data['staff_rows'] = $staffRows;
        $data['grand_totals'] = $grandTotals;
        $data['staff_count'] = count($staffRows);

        // Salary band distribution (for chart)
        $salaryBands = [
            '0 - 50,000'       => ['min' => 0, 'max' => 50000, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
            '50,001 - 100,000' => ['min' => 50001, 'max' => 100000, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
            '100,001 - 150,000'=> ['min' => 100001, 'max' => 150000, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
            '150,001 - 200,000'=> ['min' => 150001, 'max' => 200000, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
            '200,001 - 300,000'=> ['min' => 200001, 'max' => 300000, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
            '300,001+'         => ['min' => 300001, 'max' => PHP_INT_MAX, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
        ];
        foreach ($staffRows as $sr) {
            foreach ($salaryBands as $label => &$band) {
                if ($sr['basic_salary'] >= $band['min'] && $sr['basic_salary'] <= $band['max']) {
                    $band['count']++;
                    $band['total_salary'] += $sr['basic_salary'];
                    $band['total_tax'] += $sr['employee_tax_total'];
                    break;
                }
            }
        }
        $data['salary_bands'] = $salaryBands;

        // Effective tax rate stats
        $effectiveRates = collect($staffRows)->map(function ($r) {
            return $r['basic_salary'] > 0
                ? round(($r['employee_tax_total'] / $r['basic_salary']) * 100, 2)
                : 0;
        });
        $data['avg_effective_rate'] = $effectiveRates->count() > 0 ? round($effectiveRates->avg(), 2) : 0;
        $data['min_effective_rate'] = $effectiveRates->count() > 0 ? $effectiveRates->min() : 0;
        $data['max_effective_rate'] = $effectiveRates->count() > 0 ? $effectiveRates->max() : 0;

        // Historical payroll comparison (last 6 months if data exists)
        $historicalData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::today()->subMonths($i);
            $monthPayrolls = Payroll::whereYear('salary_month', $month->year)
                ->whereMonth('salary_month', $month->month)
                ->get();

            $historicalData[] = [
                'label'        => $month->format('M Y'),
                'total_gross'  => $monthPayrolls->sum('gross_salary'),
                'total_tax'    => $monthPayrolls->sum('tax'),
                'employer_tax' => $monthPayrolls->sum('employer_tax'),
                'total_net'    => $monthPayrolls->sum('net_salary'),
                'count'        => $monthPayrolls->count(),
            ];
        }
        $data['historical_data'] = $historicalData;

        return view('admin.staff-tax-report.index', $data);
    }

    /**
     * Export Staff Tax Distribution Report as PDF
     */
    public function exportPdf(Request $request)
    {
        $data['title'] = 'Staff Tax Distribution Report';

        // Filters
        $data['departments'] = Department::where('status', '1')->orderBy('title')->get();
        $data['designations'] = Designation::where('status', '1')->orderBy('title')->get();
        $data['selected_department'] = $request->department_id;
        $data['selected_designation'] = $request->designation_id;
        $data['selected_salary_min'] = $request->salary_min;
        $data['selected_salary_max'] = $request->salary_max;

        $effectiveDate = $request->effective_date ? Carbon::parse($request->effective_date) : Carbon::today();
        $data['effective_date'] = $effectiveDate->format('Y-m-d');

        // Get effective tax groups with brackets
        $taxGroups = TaxGroup::active()
            ->effectiveOn($effectiveDate)
            ->ordered()
            ->with(['brackets' => function ($q) use ($effectiveDate) {
                $q->where('status', 1)
                  ->where(function ($q2) use ($effectiveDate) {
                      $q2->whereNull('effective_from')->orWhere('effective_from', '<=', $effectiveDate);
                  })
                  ->where(function ($q2) use ($effectiveDate) {
                      $q2->whereNull('effective_to')->orWhere('effective_to', '>=', $effectiveDate);
                  })
                  ->orderBy('bracket_order')->orderBy('min_amount');
            }])
            ->get();

        $standaloneTaxes = TaxSetting::active()
            ->standalone()
            ->effectiveOn($effectiveDate)
            ->ordered()
            ->get();

        $data['tax_groups'] = $taxGroups;
        $data['standalone_taxes'] = $standaloneTaxes;

        $taxColumns = [];
        foreach ($taxGroups as $group) {
            $taxColumns[] = [
                'type'  => 'group',
                'id'    => $group->id,
                'title' => $group->title,
                'code'  => $group->code,
            ];
        }
        foreach ($standaloneTaxes as $tax) {
            $taxColumns[] = [
                'type'  => 'standalone',
                'id'    => $tax->id,
                'title' => $tax->title,
            ];
        }
        $data['tax_columns'] = $taxColumns;

        // Active staff query
        $staffQuery = User::where('status', '1')
            ->whereNotNull('basic_salary')
            ->where('basic_salary', '>', 0)
            ->with(['exemptTaxes', 'department', 'designation']);

        if ($request->department_id) {
            $staffQuery->where('department_id', $request->department_id);
        }
        if ($request->designation_id) {
            $staffQuery->where('designation_id', $request->designation_id);
        }
        if ($request->salary_min) {
            $staffQuery->where('basic_salary', '>=', $request->salary_min);
        }
        if ($request->salary_max) {
            $staffQuery->where('basic_salary', '<=', $request->salary_max);
        }

        $staff = $staffQuery->orderBy('basic_salary', 'desc')->get();

        $staffIds = $staff->pluck('id')->toArray();
        $allExemptions = StaffTaxExemption::whereIn('user_id', $staffIds)
            ->notExpired($effectiveDate)
            ->get()
            ->groupBy('user_id');

        // Calculate tax distribution (same as index)
        $staffRows = [];
        $grandTotals = [
            'basic_salary' => 0,
            'employee_tax_total' => 0,
            'employer_tax_total' => 0,
            'net_salary' => 0,
            'total_cost' => 0,
            'groups' => [],
            'standalone' => [],
        ];
        foreach ($taxGroups as $g) {
            $grandTotals['groups'][$g->id] = ['employee' => 0, 'employer' => 0];
        }
        foreach ($standaloneTaxes as $s) {
            $grandTotals['standalone'][$s->id] = ['employee' => 0, 'employer' => 0];
        }

        foreach ($staff as $user) {
            $userExemptions = isset($allExemptions[$user->id])
                ? $allExemptions[$user->id]->keyBy('tax_setting_id')
                : collect();

            $row = $this->calculateStaffTaxes($user, $taxGroups, $standaloneTaxes, $userExemptions);

            foreach ($taxGroups as $group) {
                if (isset($row['groups'][$group->id])) {
                    $grandTotals['groups'][$group->id]['employee'] += $row['groups'][$group->id]['employee'];
                    $grandTotals['groups'][$group->id]['employer'] += $row['groups'][$group->id]['employer'];
                }
            }
            foreach ($standaloneTaxes as $tax) {
                if (isset($row['standalone'][$tax->id])) {
                    $grandTotals['standalone'][$tax->id]['employee'] += $row['standalone'][$tax->id]['employee'];
                    $grandTotals['standalone'][$tax->id]['employer'] += $row['standalone'][$tax->id]['employer'];
                }
            }

            $grandTotals['basic_salary'] += $row['basic_salary'];
            $grandTotals['employee_tax_total'] += $row['employee_tax_total'];
            $grandTotals['employer_tax_total'] += $row['employer_tax_total'];
            $grandTotals['net_salary'] += $row['net_salary'];
            $grandTotals['total_cost'] += $row['total_cost'];

            $staffRows[] = $row;
        }

        $data['staff_rows'] = $staffRows;
        $data['grand_totals'] = $grandTotals;
        $data['staff_count'] = count($staffRows);

        // Salary bands
        $salaryBands = [
            '0 - 50,000'       => ['min' => 0, 'max' => 50000, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
            '50,001 - 100,000' => ['min' => 50001, 'max' => 100000, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
            '100,001 - 150,000'=> ['min' => 100001, 'max' => 150000, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
            '150,001 - 200,000'=> ['min' => 150001, 'max' => 200000, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
            '200,001 - 300,000'=> ['min' => 200001, 'max' => 300000, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
            '300,001+'         => ['min' => 300001, 'max' => PHP_INT_MAX, 'count' => 0, 'total_salary' => 0, 'total_tax' => 0],
        ];
        foreach ($staffRows as $sr) {
            foreach ($salaryBands as $label => &$band) {
                if ($sr['basic_salary'] >= $band['min'] && $sr['basic_salary'] <= $band['max']) {
                    $band['count']++;
                    $band['total_salary'] += $sr['basic_salary'];
                    $band['total_tax'] += $sr['employee_tax_total'];
                    break;
                }
            }
        }
        $data['salary_bands'] = $salaryBands;

        // Effective rates
        $effectiveRates = collect($staffRows)->map(function ($r) {
            return $r['basic_salary'] > 0
                ? round(($r['employee_tax_total'] / $r['basic_salary']) * 100, 2)
                : 0;
        });
        $data['avg_effective_rate'] = $effectiveRates->count() > 0 ? round($effectiveRates->avg(), 2) : 0;
        $data['min_effective_rate'] = $effectiveRates->count() > 0 ? $effectiveRates->min() : 0;
        $data['max_effective_rate'] = $effectiveRates->count() > 0 ? $effectiveRates->max() : 0;

        // Historical payroll (last 6 months)
        $historicalData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::today()->subMonths($i);
            $monthPayrolls = Payroll::whereYear('salary_month', $month->year)
                ->whereMonth('salary_month', $month->month)
                ->get();
            $historicalData[] = [
                'label'        => $month->format('M Y'),
                'total_gross'  => $monthPayrolls->sum('gross_salary'),
                'total_tax'    => $monthPayrolls->sum('tax'),
                'employer_tax' => $monthPayrolls->sum('employer_tax'),
                'total_net'    => $monthPayrolls->sum('net_salary'),
                'count'        => $monthPayrolls->count(),
            ];
        }
        $data['historical_data'] = $historicalData;

        // PDF-specific extras
        $data['setting'] = Setting::first();
        $data['generated_at'] = Carbon::now();
        $data['generated_by'] = auth()->user();

        // Build applied filters description
        $filterParts = [];
        if ($request->department_id) {
            $dept = Department::find($request->department_id);
            $filterParts[] = 'Department: ' . ($dept->title ?? $request->department_id);
        }
        if ($request->designation_id) {
            $desig = Designation::find($request->designation_id);
            $filterParts[] = 'Designation: ' . ($desig->title ?? $request->designation_id);
        }
        if ($request->salary_min) {
            $filterParts[] = 'Min Salary: ' . number_format($request->salary_min, 0);
        }
        if ($request->salary_max) {
            $filterParts[] = 'Max Salary: ' . number_format($request->salary_max, 0);
        }
        $filterParts[] = 'Effective Date: ' . $effectiveDate->format('F d, Y');
        $data['applied_filters'] = implode(' | ', $filterParts);

        // Get exemption details for the PDF
        $data['exemption_details'] = StaffTaxExemption::whereIn('user_id', $staffIds)
            ->notExpired($effectiveDate)
            ->with(['user.department', 'taxSetting'])
            ->get();

        $pdf = Pdf::loadView('admin.staff-tax-report.pdf', $data);
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isPhpEnabled', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        $filename = 'Staff_Tax_Distribution_Report_' . Carbon::now()->format('Y-m-d_H-i-s') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export Staff Tax Distribution Report as Excel (multi-sheet workbook)
     */
    public function exportExcel(Request $request)
    {
        $data['title'] = 'Staff Tax Distribution Report';

        // Filters
        $data['departments'] = Department::where('status', '1')->orderBy('title')->get();
        $data['designations'] = Designation::where('status', '1')->orderBy('title')->get();
        $data['selected_department'] = $request->department_id;
        $data['selected_designation'] = $request->designation_id;
        $data['selected_salary_min'] = $request->salary_min;
        $data['selected_salary_max'] = $request->salary_max;

        $effectiveDate = $request->effective_date ? Carbon::parse($request->effective_date) : Carbon::today();
        $data['effective_date'] = $effectiveDate->format('Y-m-d');

        // Get effective tax groups with brackets
        $taxGroups = TaxGroup::active()
            ->effectiveOn($effectiveDate)
            ->ordered()
            ->with(['brackets' => function ($q) use ($effectiveDate) {
                $q->where('status', 1)
                  ->where(function ($q2) use ($effectiveDate) {
                      $q2->whereNull('effective_from')->orWhere('effective_from', '<=', $effectiveDate);
                  })
                  ->where(function ($q2) use ($effectiveDate) {
                      $q2->whereNull('effective_to')->orWhere('effective_to', '>=', $effectiveDate);
                  })
                  ->orderBy('bracket_order')->orderBy('min_amount');
            }])
            ->get();

        $standaloneTaxes = TaxSetting::active()
            ->standalone()
            ->effectiveOn($effectiveDate)
            ->ordered()
            ->get();

        $data['tax_groups'] = $taxGroups;
        $data['standalone_taxes'] = $standaloneTaxes;

        $taxColumns = [];
        foreach ($taxGroups as $group) {
            $taxColumns[] = [
                'type'  => 'group',
                'id'    => $group->id,
                'title' => $group->title,
                'code'  => $group->code,
            ];
        }
        foreach ($standaloneTaxes as $tax) {
            $taxColumns[] = [
                'type'  => 'standalone',
                'id'    => $tax->id,
                'title' => $tax->title,
            ];
        }
        $data['tax_columns'] = $taxColumns;

        // Active staff query
        $staffQuery = User::where('status', '1')
            ->whereNotNull('basic_salary')
            ->where('basic_salary', '>', 0)
            ->with(['exemptTaxes', 'department', 'designation']);

        if ($request->department_id) {
            $staffQuery->where('department_id', $request->department_id);
        }
        if ($request->designation_id) {
            $staffQuery->where('designation_id', $request->designation_id);
        }
        if ($request->salary_min) {
            $staffQuery->where('basic_salary', '>=', $request->salary_min);
        }
        if ($request->salary_max) {
            $staffQuery->where('basic_salary', '<=', $request->salary_max);
        }

        $staff = $staffQuery->orderBy('basic_salary', 'desc')->get();

        $staffIds = $staff->pluck('id')->toArray();
        $allExemptions = StaffTaxExemption::whereIn('user_id', $staffIds)
            ->notExpired($effectiveDate)
            ->get()
            ->groupBy('user_id');

        // Calculate tax distribution
        $staffRows = [];
        $grandTotals = [
            'basic_salary' => 0,
            'employee_tax_total' => 0,
            'employer_tax_total' => 0,
            'net_salary' => 0,
            'total_cost' => 0,
            'groups' => [],
            'standalone' => [],
        ];
        foreach ($taxGroups as $g) {
            $grandTotals['groups'][$g->id] = ['employee' => 0, 'employer' => 0];
        }
        foreach ($standaloneTaxes as $s) {
            $grandTotals['standalone'][$s->id] = ['employee' => 0, 'employer' => 0];
        }

        foreach ($staff as $user) {
            $userExemptions = isset($allExemptions[$user->id])
                ? $allExemptions[$user->id]->keyBy('tax_setting_id')
                : collect();

            $row = $this->calculateStaffTaxes($user, $taxGroups, $standaloneTaxes, $userExemptions);

            foreach ($taxGroups as $group) {
                if (isset($row['groups'][$group->id])) {
                    $grandTotals['groups'][$group->id]['employee'] += $row['groups'][$group->id]['employee'];
                    $grandTotals['groups'][$group->id]['employer'] += $row['groups'][$group->id]['employer'];
                }
            }
            foreach ($standaloneTaxes as $tax) {
                if (isset($row['standalone'][$tax->id])) {
                    $grandTotals['standalone'][$tax->id]['employee'] += $row['standalone'][$tax->id]['employee'];
                    $grandTotals['standalone'][$tax->id]['employer'] += $row['standalone'][$tax->id]['employer'];
                }
            }

            $grandTotals['basic_salary'] += $row['basic_salary'];
            $grandTotals['employee_tax_total'] += $row['employee_tax_total'];
            $grandTotals['employer_tax_total'] += $row['employer_tax_total'];
            $grandTotals['net_salary'] += $row['net_salary'];
            $grandTotals['total_cost'] += $row['total_cost'];

            $staffRows[] = $row;
        }

        $data['staff_rows'] = $staffRows;
        $data['grand_totals'] = $grandTotals;
        $data['staff_count'] = count($staffRows);

        // Salary bands with employee + employer tax
        $salaryBands = [
            '0 - 50,000'       => ['min' => 0, 'max' => 50000, 'count' => 0, 'total_salary' => 0, 'total_employee_tax' => 0, 'total_employer_tax' => 0],
            '50,001 - 100,000' => ['min' => 50001, 'max' => 100000, 'count' => 0, 'total_salary' => 0, 'total_employee_tax' => 0, 'total_employer_tax' => 0],
            '100,001 - 150,000'=> ['min' => 100001, 'max' => 150000, 'count' => 0, 'total_salary' => 0, 'total_employee_tax' => 0, 'total_employer_tax' => 0],
            '150,001 - 200,000'=> ['min' => 150001, 'max' => 200000, 'count' => 0, 'total_salary' => 0, 'total_employee_tax' => 0, 'total_employer_tax' => 0],
            '200,001 - 300,000'=> ['min' => 200001, 'max' => 300000, 'count' => 0, 'total_salary' => 0, 'total_employee_tax' => 0, 'total_employer_tax' => 0],
            '300,001+'         => ['min' => 300001, 'max' => PHP_INT_MAX, 'count' => 0, 'total_salary' => 0, 'total_employee_tax' => 0, 'total_employer_tax' => 0],
        ];
        foreach ($staffRows as $sr) {
            foreach ($salaryBands as $label => &$band) {
                if ($sr['basic_salary'] >= $band['min'] && $sr['basic_salary'] <= $band['max']) {
                    $band['count']++;
                    $band['total_salary'] += $sr['basic_salary'];
                    $band['total_employee_tax'] += $sr['employee_tax_total'];
                    $band['total_employer_tax'] += $sr['employer_tax_total'];
                    break;
                }
            }
        }
        $data['salary_bands'] = $salaryBands;

        // Effective rates
        $effectiveRates = collect($staffRows)->map(function ($r) {
            return $r['basic_salary'] > 0
                ? round(($r['employee_tax_total'] / $r['basic_salary']) * 100, 2)
                : 0;
        });
        $data['avg_effective_rate'] = $effectiveRates->count() > 0 ? round($effectiveRates->avg(), 2) : 0;
        $data['min_effective_rate'] = $effectiveRates->count() > 0 ? $effectiveRates->min() : 0;
        $data['max_effective_rate'] = $effectiveRates->count() > 0 ? $effectiveRates->max() : 0;

        // Historical payroll (last 6 months)
        $historicalData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::today()->subMonths($i);
            $monthPayrolls = Payroll::whereYear('salary_month', $month->year)
                ->whereMonth('salary_month', $month->month)
                ->get();
            $historicalData[] = [
                'label'        => $month->format('M Y'),
                'total_gross'  => $monthPayrolls->sum('gross_salary'),
                'total_tax'    => $monthPayrolls->sum('tax'),
                'employer_tax' => $monthPayrolls->sum('employer_tax'),
                'total_net'    => $monthPayrolls->sum('net_salary'),
                'count'        => $monthPayrolls->count(),
            ];
        }
        $data['historical_data'] = $historicalData;

        // Excel-specific extras
        $data['setting'] = Setting::first();
        $data['generated_at'] = Carbon::now();
        $data['generated_by'] = auth()->user();

        // Build applied filters description
        $filterParts = [];
        if ($request->department_id) {
            $dept = Department::find($request->department_id);
            $filterParts[] = 'Department: ' . ($dept->title ?? $request->department_id);
        }
        if ($request->designation_id) {
            $desig = Designation::find($request->designation_id);
            $filterParts[] = 'Designation: ' . ($desig->title ?? $request->designation_id);
        }
        if ($request->salary_min) {
            $filterParts[] = 'Min Salary: ' . number_format($request->salary_min, 0);
        }
        if ($request->salary_max) {
            $filterParts[] = 'Max Salary: ' . number_format($request->salary_max, 0);
        }
        $filterParts[] = 'Effective Date: ' . $effectiveDate->format('F d, Y');
        $data['applied_filters'] = implode(' | ', $filterParts);

        // Get exemption details
        $data['exemption_details'] = StaffTaxExemption::whereIn('user_id', $staffIds)
            ->notExpired($effectiveDate)
            ->with(['user.department', 'taxSetting'])
            ->get();

        $filename = 'Staff_Tax_Distribution_Report_' . Carbon::now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new StaffTaxReportExport($data), $filename);
    }

    /**
     * Calculate taxes for a single staff member, handling dependent taxes.
     * Returns the row data with group and standalone results.
     *
     * @param \App\User $user
     * @param \Illuminate\Support\Collection $taxGroups
     * @param \Illuminate\Support\Collection $standaloneTaxes (all, including dependent)
     * @param \Illuminate\Support\Collection $userExemptions keyed by tax_setting_id
     * @return array
     */
    private function calculateStaffTaxes($user, $taxGroups, $standaloneTaxes, $userExemptions)
    {
        $salary = (float) $user->basic_salary;

        $row = [
            'user'                => $user,
            'basic_salary'        => $salary,
            'groups'              => [],
            'standalone'          => [],
            'employee_tax_total'  => 0,
            'employer_tax_total'  => 0,
            'net_salary'          => 0,
            'total_cost'          => 0,
            'exemption_count'     => 0,
        ];

        // Store results for dependent tax resolution
        $groupResults = [];

        // === PASS 1: Calculate group taxes ===
        foreach ($taxGroups as $group) {
            $result = $group->calculateTax($salary, $userExemptions);
            $employeeTax = $result['amount'];

            // Employer contribution for the SAME single applicable bracket that
            // calculateTax() selected (step-lookup), so employee/employer agree.
            $employerTax = 0;
            $appBracket = $group->applicableBracket($salary);
            if ($appBracket && $appBracket->paid_by !== 'employee') {
                $isExempt = $userExemptions->has($appBracket->id);
                if ($isExempt && $userExemptions->get($appBracket->id)->isExpired()) {
                    $isExempt = false;
                }
                if (!$isExempt) {
                    $employerTax = $appBracket->calculateEmployerContribution($salary);
                }
            }

            $groupResults[$group->id] = [
                'employee' => $employeeTax,
                'employer' => $employerTax,
            ];

            $row['groups'][$group->id] = [
                'employee'   => round($employeeTax, 2),
                'employer'   => round($employerTax, 2),
                'breakdown'  => $result['breakdown'],
            ];

            $row['employee_tax_total'] += $employeeTax;
            $row['employer_tax_total'] += $employerTax;
        }

        // Partition standalone taxes into base and dependent
        $baseTaxes = $standaloneTaxes->filter(function ($t) { return !$t->is_dependent; });
        $dependentTaxes = $standaloneTaxes->filter(function ($t) { return $t->is_dependent; });

        // Store standalone results for dependent resolution
        $standaloneResults = [];

        // === PASS 2: Calculate base standalone taxes ===
        foreach ($baseTaxes as $tax) {
            $isExempt = $userExemptions->has($tax->id);
            if ($isExempt && $userExemptions->get($tax->id)->isExpired()) {
                $isExempt = false;
            }

            $employeeTax = 0;
            $employerTax = 0;

            if ($salary >= $tax->min_amount && $salary <= $tax->max_amount) {
                if ($isExempt) {
                    $row['exemption_count']++;
                    // Honor a custom exemption rate/amount (mirrors payroll). A plain
                    // exemption (no custom value) means fully exempt → 0. Employer side
                    // is 0 when exempt, matching the payslip.
                    $exemption = $userExemptions->get($tax->id);
                    if ($exemption && $exemption->custom_percentage && $tax->tax_type == 1) {
                        $taxable = max(0, $salary - ($tax->max_no_taxable_amount ?? 0));
                        $employeeTax = ($taxable / 100) * $exemption->custom_percentage;
                    } elseif ($exemption && $exemption->custom_fixed_amount && $tax->tax_type == 2) {
                        $employeeTax = $exemption->custom_fixed_amount;
                    }
                } else {
                    $employeeTax = $tax->calculateEmployeeContribution($salary);
                    $employerTax = $tax->calculateEmployerContribution($salary);
                }
            } elseif ($isExempt) {
                $row['exemption_count']++;
            }

            $standaloneResults[$tax->id] = [
                'employee' => $employeeTax,
                'employer' => $employerTax,
            ];

            $row['standalone'][$tax->id] = [
                'employee' => round($employeeTax, 2),
                'employer' => round($employerTax, 2),
                'is_exempt' => $isExempt,
            ];

            $row['employee_tax_total'] += $employeeTax;
            $row['employer_tax_total'] += $employerTax;
        }

        // === PASS 3: Calculate dependent standalone taxes ===
        foreach ($dependentTaxes as $depTax) {
            $isExempt = $userExemptions->has($depTax->id);
            if ($isExempt && $userExemptions->get($depTax->id)->isExpired()) {
                $isExempt = false;
            }

            $employeeTax = 0;
            $employerTax = 0;

            // Resolve source tax amount
            $sourceAmount = 0;
            if ($depTax->depends_on_type === 'tax_group' && isset($groupResults[$depTax->depends_on_id])) {
                $src = $groupResults[$depTax->depends_on_id];
                $sourceAmount = $src['employee'] + $src['employer'];
            } elseif ($depTax->depends_on_type === 'tax_setting' && isset($standaloneResults[$depTax->depends_on_id])) {
                $src = $standaloneResults[$depTax->depends_on_id];
                $sourceAmount = $src['employee'] + $src['employer'];
            }

            // Check salary range for applicability
            if ($salary >= $depTax->min_amount && $salary <= $depTax->max_amount) {
                if ($isExempt) {
                    $row['exemption_count']++;
                    // Honor a custom exemption rate/amount on the source amount (mirrors
                    // payroll); plain exemption → 0; employer side 0 when exempt.
                    $exemption = $userExemptions->get($depTax->id);
                    if ($exemption && $exemption->custom_percentage && $depTax->tax_type == 1) {
                        $employeeTax = ($sourceAmount / 100) * $exemption->custom_percentage;
                    } elseif ($exemption && $exemption->custom_fixed_amount && $depTax->tax_type == 2) {
                        $employeeTax = $exemption->custom_fixed_amount;
                    }
                } else {
                    // Calculate from source amount instead of salary
                    $employeeTax = $depTax->calculateEmployeeContribution($sourceAmount);
                    $employerTax = $depTax->calculateEmployerContribution($sourceAmount);
                }
            } elseif ($isExempt) {
                $row['exemption_count']++;
            }

            $standaloneResults[$depTax->id] = [
                'employee' => $employeeTax,
                'employer' => $employerTax,
            ];

            $row['standalone'][$depTax->id] = [
                'employee' => round($employeeTax, 2),
                'employer' => round($employerTax, 2),
                'is_exempt' => $isExempt,
                'is_dependent' => true,
                'source_amount' => $sourceAmount,
            ];

            $row['employee_tax_total'] += $employeeTax;
            $row['employer_tax_total'] += $employerTax;
        }

        $row['employee_tax_total'] = round($row['employee_tax_total'], 2);
        $row['employer_tax_total'] = round($row['employer_tax_total'], 2);
        $row['net_salary'] = round($salary - $row['employee_tax_total'], 2);
        $row['total_cost'] = round($salary + $row['employer_tax_total'], 2);

        return $row;
    }
}
