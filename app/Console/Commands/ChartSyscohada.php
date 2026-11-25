<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puts the chart of accounts on the standard SYSCOHADA numbering (2017
 * revision), so the statements and the annual tax return (DSF) read the way
 * an auditor or the tax office expects.
 *
 * The chart the school started with used several numbers for something else:
 * tuition under 71 (subsidies), grants under 73 (changes in stock), salary
 * withholdings under 443 (VAT charged), mobile money under 53, the year's
 * result under 12 (retained earnings), and so on.
 *
 * Only the NUMBER and the NAME of an account change. Its id stays, so every
 * posted line, category, budget mapping, fixed-asset category and setting
 * keeps pointing at it, and balances do not move.
 *
 * Safe to run more than once. An account whose name does not look like the
 * one expected (renamed by hand on this install) is left alone and listed, so
 * nothing is renumbered on a guess. Without --apply nothing is written.
 */
class ChartSyscohada extends Command
{
    protected $signature = 'accounts:syscohada {--apply : Renumber (without it, only show what would change)}';

    protected $description = 'Renumber the chart of accounts to standard SYSCOHADA codes (preview unless --apply)';

    /**
     * [old code, words its name must contain (any), new code, new name (accented), new parent code].
     * Order does not matter: every account is moved through a temporary code.
     */
    public const MOVES = [
        // Class 1
        ['12', ['resultat'], '13', "Résultat net de l'exercice", '1'],
        // Class 2
        ['28', ['amortissement'], '284', 'Amortissements du matériel', '28'],
        // Class 4
        ['40', ['fournisseur'], '401', 'Fournisseurs, dettes en compte', '40'],
        ['412', ['avance'], '421', 'Personnel, avances et acomptes', '42'],
        ['421', ['salaire', 'remuneration'], '422', 'Personnel, rémunérations dues', '42'],
        ['441', ['tva'], '443', 'État, TVA facturée', '44'],
        ['443', ['retenue', 'irpp', 'impot'], '447', 'État, impôts retenus à la source', '44'],
        // Class 5
        ['53', ['mobile', 'money', 'electronique'], '552', 'Mobile Money (monnaie électronique)', '55'],
        // Class 6: purchases
        ['605', ['eau', 'electricite'], '6051', 'Eau et électricité', '605'],
        ['601', ['bureau'], '6055', 'Fournitures de bureau', '605'],
        ['602', ['pedagogique', 'materiel'], '6056', 'Petit matériel et matériel pédagogique', '605'],
        ['606', ['carburant', 'lubrifiant'], '6053', 'Carburants et lubrifiants', '605'],
        ['607', ['entretien', 'hygiene'], '6054', "Produits d'entretien et d'hygiène", '605'],
        ['608', ['agricole', 'projet'], '602', 'Achats de matières premières (projets agricoles)', '60'],
        // Class 6: transport and external services
        ['633', ['transport', 'deplacement'], '618', 'Voyages et déplacements', '61'],
        ['638', ['documentation', 'bibliotheque'], '626', 'Études, recherches et documentation', '62'],
        ['635', ['publicite', 'communication'], '627', 'Publicité, publications et relations publiques', '62'],
        ['626', ['telecom', 'internet'], '628', 'Télécommunications et internet', '62'],
        ['627', ['bancaire', 'banque'], '631', 'Frais bancaires', '63'],
        ['631', ['formation', 'seminaire'], '633', 'Formation du personnel', '63'],
        ['636', ['securite', 'gardiennage'], '637', 'Personnel extérieur (sécurité et gardiennage)', '63'],
        ['632', ['recrutement'], '6381', 'Frais de recrutement du personnel', '638'],
        ['634', ['reception', 'ceremonie'], '6383', 'Réceptions et cérémonies', '638'],
        ['628', ['autres services', 'exterieur'], '6388', 'Autres charges externes', '638'],
        // Class 6: staff
        ['663', ['oeuvre', 'social'], '6681', 'Œuvres sociales du personnel', '668'],
        ['637', ['medica', 'laboratoire'], '6682', 'Frais médicaux et de laboratoire', '668'],
        ['662', ['indemnite', 'prime'], '663', 'Indemnités et primes', '66'],
        // Class 6: depreciation
        ['681', ['materiel'], '68131', 'Amortissement du matériel', '681'],
        ['682', ['batiment'], '68132', 'Amortissement des bâtiments', '681'],
        ['683', ['transport'], '68133', 'Amortissement du matériel de transport', '681'],
        // Class 7: tuition is a service sold (706), not a subsidy (71)
        ['706', ['services vendus'], '7068', 'Autres services vendus', '706'],
        ['711', ['scolarite'], '7061', 'Frais de scolarité', '706'],
        ['712', ['inscription'], '7062', "Frais d'inscription", '706'],
        ['713', ['examen'], '7063', "Frais d'examen", '706'],
        ['73', ['subvention'], '718', "Subventions d'exploitation reçues", '71'],
        ['751', ['projet'], '702', 'Ventes de produits (projets agricoles)', '70'],
    ];

    /**
     * Accounts the standard layout needs and the old chart lacked:
     * [code, name, parent code, class, type, normal balance, category].
     */
    public const CREATES = [
        ['12', 'Report à nouveau', '1', 1, 'equity', 'credit', 'detail'],
        ['28', 'Amortissements des immobilisations', '2', 2, 'asset', 'credit', 'heading'],
        ['40', 'Fournisseurs et comptes rattachés', '4', 4, 'liability', 'credit', 'heading'],
        ['55', 'Instruments de monnaie électronique', '5', 5, 'asset', 'debit', 'heading'],
        ['605', 'Autres achats (fournitures non stockées)', '60', 6, 'expense', 'debit', 'heading'],
        ['638', 'Autres charges externes', '63', 6, 'expense', 'debit', 'heading'],
        ['65', 'Autres charges', '6', 6, 'expense', 'debit', 'heading'],
        ['668', 'Autres charges sociales', '66', 6, 'expense', 'debit', 'heading'],
        ['681', "Dotations aux amortissements d'exploitation", '68', 6, 'expense', 'debit', 'heading'],
        ['69', 'Dotations aux provisions', '6', 6, 'expense', 'debit', 'heading'],
        ['706', 'Services vendus (scolarité)', '70', 7, 'revenue', 'credit', 'heading'],
    ];

    /** Accounts that keep their number but get a standard parent: [code, parent]. */
    public const REPARENT = [
        ['408', '40'], ['651', '65'], ['658', '65'], ['691', '69'], ['697', '69'],
    ];

    /** Headings that keep their number but change meaning: [code, words now (any), new name]. */
    public const RENAMES = [
        ['71', ['scolarite'], "Subventions d'exploitation"],
        ['41', ['eleve', 'parent'], 'Clients (élèves et parents)'],
        ['61', ['transport'], 'Transports'],
        ['63', ['autres services'], 'Services extérieurs B'],
        ['62', ['services exterieurs'], 'Services extérieurs A'],
    ];

    /** Detail accounts that become headings once nothing is posted on them. */
    public const TO_HEADING = ['61'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $byCode = fn () => DB::table('chart_of_accounts')->whereNull('deleted_at')->get()->keyBy('account_code');
        $accounts = $byCode();
        $used = DB::table('journal_entry_lines')->selectRaw('account_id, COUNT(*) n')->groupBy('account_id')->pluck('n', 'account_id');

        $rows = [];
        $moves = [];
        $problems = [];
        $movingAway = [];

        foreach (self::MOVES as [$from, $words, $to, $name, $parent]) {
            $target = $accounts->get($to);
            if ($target && $this->same($target->account_name_fr ?: $target->account_name, $name)) {
                $rows[] = [$from, '', $to, $name, $used[$target->id] ?? 0, 'already done'];
                continue;
            }
            $a = $accounts->get($from);
            if (!$a) {
                $rows[] = [$from, '—', $to, $name, 0, 'not in this chart: skipped'];
                continue;
            }
            $current = $a->account_name_fr ?: $a->account_name;
            if (!$this->matches($current, $words) && !$this->matches($a->account_name, $words)) {
                $rows[] = [$from, $current, $to, $name, $used[$a->id] ?? 0, 'NAME DIFFERS: left alone'];
                $problems[] = "$from \"$current\" does not look like the account to become $to ($name). Rename or renumber it by hand.";
                continue;
            }
            $moves[] = [$a, $to, $name, $parent];
            $movingAway[$from] = true;
            $rows[] = [$from, $current, $to, $name, $used[$a->id] ?? 0, 'renumber'];
        }

        // A target code taken by an account that is not itself moving away would collide.
        foreach ($moves as [$a, $to]) {
            if ($accounts->has($to) && empty($movingAway[$to])) {
                $other = $accounts->get($to);
                $problems[] = "$to is already used by \"" . ($other->account_name_fr ?: $other->account_name) . "\" (id {$other->id}), so {$a->account_code} cannot move there.";
            }
        }
        foreach (self::CREATES as [$code, $name]) {
            if ($accounts->has($code) && empty($movingAway[$code]) && !$this->same($accounts->get($code)->account_name_fr ?: $accounts->get($code)->account_name, $name)) {
                $other = $accounts->get($code);
                $problems[] = "$code should become \"$name\" but is used by \"" . ($other->account_name_fr ?: $other->account_name) . '".';
            }
        }

        $this->table(['Old', 'Old name', 'New', 'New name', 'Lines posted', 'What happens'], $rows);
        $creates = collect(self::CREATES)->filter(fn ($c) => !$accounts->has($c[0]) || !empty($movingAway[$c[0]]))->map(fn ($c) => [$c[0], $c[1], $c[6]])->values()->all();
        if ($creates) {
            $this->line('New accounts the standard layout needs:');
            $this->table(['Code', 'Name', 'Kind'], $creates);
        }

        if ($problems) {
            $this->newLine();
            $this->error('Not applied: settle these first.');
            foreach ($problems as $p) {
                $this->line('  - ' . $p);
            }

            return self::FAILURE;
        }

        if (!$apply) {
            $this->newLine();
            $this->info(count($moves) . ' accounts would be renumbered, ' . count($creates) . ' added. Balances and posted entries do not change.');
            $this->line('Nothing written. Run again with --apply to renumber.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($moves, $byCode, $used) {
            $now = now();
            // 1. Out of the way: every moving account on a temporary code.
            foreach ($moves as [$a]) {
                DB::table('chart_of_accounts')->where('id', $a->id)->update(['account_code' => 'TMP' . $a->id]);
            }
            // 2. Their new numbers and names.
            foreach ($moves as [$a, $to, $name]) {
                DB::table('chart_of_accounts')->where('id', $a->id)->update([
                    'account_code' => $to, 'account_name' => Str::ascii($name), 'account_name_fr' => $name, 'updated_at' => $now,
                ]);
            }
            // 3. The headings and accounts the layout needs.
            $accounts = $byCode();
            foreach (self::CREATES as [$code, $name, $parent, $class, $type, $normal, $category]) {
                if ($accounts->has($code)) {
                    continue;
                }
                DB::table('chart_of_accounts')->insert([
                    'account_code' => $code, 'account_name' => Str::ascii($name), 'account_name_fr' => $name,
                    'parent_id' => $accounts->get($parent)?->id, 'class_number' => $class, 'account_type' => $type,
                    'account_category' => $category, 'normal_balance' => $normal, 'opening_balance' => 0, 'current_balance' => 0,
                    'is_active' => 1, 'is_system' => 1, 'display_order' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            // 4. Parents, now that every code exists.
            $accounts = $byCode();
            $setParent = function (string $code, string $parent) use ($accounts) {
                $a = $accounts->get($code);
                $p = $accounts->get($parent);
                if ($a && $p && (int) $a->parent_id !== (int) $p->id) {
                    DB::table('chart_of_accounts')->where('id', $a->id)->update(['parent_id' => $p->id]);
                }
            };
            foreach ($moves as [, $to, , $parent]) {
                $setParent($to, $parent);
            }
            foreach (self::CREATES as [$code, , $parent]) {
                $setParent($code, $parent);
            }
            foreach (self::REPARENT as [$code, $parent]) {
                $setParent($code, $parent);
            }
            // 5. Headings whose meaning changes, and details that become headings.
            foreach (self::RENAMES as [$code, $words, $name]) {
                $a = $accounts->get($code);
                if ($a && ($this->matches($a->account_name_fr ?: $a->account_name, $words) || $this->matches($a->account_name, $words))) {
                    DB::table('chart_of_accounts')->where('id', $a->id)->update(['account_name' => Str::ascii($name), 'account_name_fr' => $name]);
                }
            }
            foreach (self::TO_HEADING as $code) {
                $a = $accounts->get($code);
                if ($a && $a->account_category === 'detail' && empty($used[$a->id])) {
                    DB::table('chart_of_accounts')->where('id', $a->id)->update(['account_category' => 'heading']);
                }
            }

            DB::table('accounting_settings')->updateOrInsert(['setting_key' => 'chart_syscohada_applied_at'], [
                'setting_value' => $now->toDateTimeString(), 'setting_type' => 'string', 'category' => 'general',
                'description' => 'When the chart was renumbered to standard SYSCOHADA codes (accounts:syscohada)',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });

        $this->info(count($moves) . ' accounts renumbered. Balances and posted entries are unchanged.');

        return self::SUCCESS;
    }

    /*
     | The seeders define the chart in the old numbering and pass it through
     | these, so a fresh install and a renumbered one end up with the same
     | chart, from one list.
     */

    /** A seeder row keyed like OhadaChartOfAccountsSeeder's, in the standard numbering. */
    public static function standardiseRow(array $row): array
    {
        $code = (string) $row['account_code'];
        foreach (self::MOVES as [$from, , $to, $name, $parent]) {
            if ($code === $from) {
                $row['account_code'] = $to;
                $row['account_name'] = Str::ascii($name);
                $row['account_name_fr'] = $name;
                $row['parent_code'] = $parent;
                $row['account_category'] = 'detail';

                return $row;
            }
        }
        foreach (self::REPARENT as [$c, $parent]) {
            if ($code === $c) {
                $row['parent_code'] = $parent;
            }
        }
        foreach (self::RENAMES as [$c, , $name]) {
            if ($code === $c) {
                $row['account_name'] = Str::ascii($name);
                $row['account_name_fr'] = $name;
            }
        }
        if (in_array($code, self::TO_HEADING, true)) {
            $row['account_category'] = 'heading';
        }

        return $row;
    }

    /** The accounts the standard layout adds, as seeder rows. */
    public static function createdRows(): array
    {
        return array_map(fn ($c) => [
            'account_code' => $c[0], 'account_name' => Str::ascii($c[1]), 'account_name_fr' => $c[1], 'parent_code' => $c[2],
            'class_number' => $c[3], 'account_type' => $c[4], 'normal_balance' => $c[5], 'account_category' => $c[6],
            'is_system' => true, 'is_active' => true,
        ], self::CREATES);
    }

    /** Detail-seeder tuples [code, name, name_fr, parent, type, normal], in the standard numbering. */
    public static function standardiseTuples(array $tuples): array
    {
        return array_map(function ($t) {
            [$code, $name, $nameFr, $parent, $type, $normal] = $t;
            $row = self::standardiseRow(['account_code' => $code, 'account_name' => $name, 'account_name_fr' => $nameFr,
                'parent_code' => $parent, 'account_category' => 'detail']);

            return [$row['account_code'], $row['account_name'], $row['account_name_fr'], $row['parent_code'], $type, $normal];
        }, $tuples);
    }

    /** The standard code for an old one (unchanged when it did not move). */
    public static function code(string $old): string
    {
        foreach (self::MOVES as [$from, , $to]) {
            if ($from === $old) {
                return $to;
            }
        }

        return $old;
    }

    /**
     * The code an account has on THIS install: the standard one once the chart
     * is on the standard numbering (renumbered, or installed fresh), the old one
     * before. Code that looks accounts up by number goes through this, so it
     * works on both sides of the change.
     */
    public static function current(string $old): string
    {
        // Not cached: the answer changes the moment --apply runs, or while a
        // fresh install is seeding, and a stale answer finds the wrong account.
        return self::isStandard() ? self::code($old) : $old;
    }

    /** The chart is on the standard numbering (renumbered, or installed fresh). */
    public static function isStandard(): bool
    {
        return DB::table('chart_of_accounts')->whereNull('deleted_at')->where('account_code', '7061')->exists();
    }

    /**
     * Seeders define the standard chart only on an empty chart or one already
     * renumbered. On a chart still on the old numbering they keep the old one:
     * seeding standard accounts there would add twins beside the old accounts
     * (a 7061 next to 711), and accounts:syscohada would then refuse to move
     * onto codes already taken. Renumber first; the seeders follow.
     */
    public static function seedsStandard(): bool
    {
        return self::isStandard() || !DB::table('chart_of_accounts')->whereNull('deleted_at')->exists();
    }

    /** Seeder rows (OhadaChartOfAccountsSeeder's shape) for this install. */
    public static function seederRows(array $original): array
    {
        return self::seedsStandard()
            ? array_merge(array_map([self::class, 'standardiseRow'], $original), self::createdRows())
            : $original;
    }

    /** Detail-seeder tuples for this install. */
    public static function seederTuples(array $original): array
    {
        return self::seedsStandard() ? self::standardiseTuples($original) : $original;
    }

    private function norm(?string $s): string
    {
        return Str::lower(Str::ascii((string) $s));
    }

    private function matches(?string $name, array $words): bool
    {
        $n = $this->norm($name);
        foreach ($words as $w) {
            if (str_contains($n, $this->norm($w))) {
                return true;
            }
        }

        return false;
    }

    private function same(?string $a, string $b): bool
    {
        return $this->norm($a) === $this->norm($b);
    }
}
