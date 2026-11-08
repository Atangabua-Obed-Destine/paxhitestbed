<?php

namespace App\Services\EdutrustPay;

use App\Models\BudgetLine;
use App\Services\DaybookService;
use Carbon\Carbon;
use EdutrustPay\Contract\Daybook;
use EdutrustPay\Contract\Money;
use Illuminate\Support\Facades\DB;

/**
 * This institution's daybook for one month, in the shape the console receives.
 *
 * The lines come from DaybookService, the same book the bursar reads here, so the
 * console can never hold a different book. Nothing is typed in or edited on the way.
 *
 * THESE LINES NAME PEOPLE. The body chose to hold them, and the settings screen
 * says so to whoever runs this institution.
 */
class DaybookExporter
{
    /** The codes the application uses for payment_method, per admin/report/*.blade.php. */
    private const METHODS = [
        1 => 'card', 2 => 'cash', 3 => 'cheque', 4 => 'bank', 5 => 'mobile_money',
        6 => 'online', 7 => 'online', 8 => 'online', 9 => 'online', 10 => 'online',
    ];

    public function __construct(private DaybookService $daybook)
    {
    }

    /**
     * @param  string  $period  calendar month, "YYYY-MM"
     * @return array<string, mixed>
     */
    public function document(string $period, int $sequence = 1): array
    {
        $settings = app(SettingsResolver::class)->resolve();

        if ($settings === null) {
            throw new \RuntimeException('No EdutrustPay credentials are configured.');
        }

        $month = Carbon::createFromFormat('!Y-m', $period);
        $rows = $this->daybook->rows($month->toDateString(), $month->copy()->endOfMonth()->toDateString());

        $headings = BudgetLine::query()
            ->whereIn('id', array_filter(array_column($rows, 'line_id')))
            ->pluck('name', 'id');

        $methods = $this->methods($rows);

        $lines = array_map(function (array $row) use ($headings, $methods) {
            $party = null;

            // A fee line reads "Name — Category". The name is who paid.
            if ($row['source'] === 'fee' && str_contains($row['description'], ' — ')) {
                $party = explode(' — ', $row['description'], 2)[0];
            }

            return [
                'date' => substr((string) $row['date'], 0, 10),
                'ref' => (string) $row['ref'],
                'description' => (string) $row['description'],
                'party' => $party,
                'heading' => (string) ($headings[$row['line_id']] ?? __('Not on a budget line')),
                'kind' => in_array($row['source'], ['fee', 'income', 'expense', 'payroll'], true) ? $row['source'] : 'other',
                'direction' => $row['direction'],
                'amount' => Money::of(number_format((float) $row['amount'], 2, '.', '')),
                'method' => $methods[$row['source']][$row['source_id']] ?? null,
                'source_id' => $row['source'].':'.$row['source_id'],
            ];
        }, $rows);

        return Daybook::build($settings['institution_ref'], $period, $sequence, gmdate('Y-m-d\TH:i:s\Z'), 'XAF', $lines);
    }

    /**
     * How each line was paid, looked up by where the line came from.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int, string>>
     */
    private function methods(array $rows): array
    {
        $found = [];

        foreach (['fee' => 'fees', 'income' => 'incomes', 'expense' => 'expenses'] as $source => $table) {
            $ids = array_column(array_filter($rows, fn (array $row) => $row['source'] === $source), 'source_id');

            $found[$source] = DB::table($table)->whereIn('id', $ids)->pluck('payment_method', 'id')
                ->map(fn ($code) => $code === null ? null : (self::METHODS[(int) $code] ?? 'other'))
                ->filter()
                ->all();
        }

        return $found;
    }
}
