<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StaffTaxReportExport implements WithMultipleSheets
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function sheets(): array
    {
        return [
            'Tax Distribution'  => new StaffTaxReportSheets\DistributionSheet($this->data),
            'Effective Rates'   => new StaffTaxReportSheets\EffectiveRatesSheet($this->data),
            'Salary Bands'      => new StaffTaxReportSheets\SalaryBandsSheet($this->data),
            'Tax Breakdown'     => new StaffTaxReportSheets\TaxBreakdownSheet($this->data),
            'Tax Configuration' => new StaffTaxReportSheets\TaxConfigSheet($this->data),
            'Exemptions'        => new StaffTaxReportSheets\ExemptionsSheet($this->data),
            'Historical Trend'  => new StaffTaxReportSheets\HistoricalSheet($this->data),
        ];
    }
}
