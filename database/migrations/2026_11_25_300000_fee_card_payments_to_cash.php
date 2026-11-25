<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Fees and fee receipts stored as "Card" (1) were cash: the school takes no
 * card payments, and the lang file had long relabelled Card as "Cash", so the
 * forms offered "Cash" twice and staff picked either. Move them to Cash (2).
 *
 * Payment plans number their own methods (5 = card, 1 = cash).
 *
 * The ledger is unaffected: fee postings treat 1 and 2 alike as cash
 * (CashAccounts::SCHEME_LEGACY).
 *
 * The ids changed are written to storage/app/migrations/ so down() puts back
 * exactly those rows and no others.
 */
return new class extends Migration
{
    private const LOG = 'migrations/2026_11_25_fee_card_payments_to_cash.json';

    private const CHANGES = [
        // table => [from, to]
        'fees' => [1, 2],
        'payment_receipts' => [1, 2],
        'payment_plan_payments' => [5, 1],
    ];

    public function up(): void
    {
        $log = [];

        DB::transaction(function () use (&$log) {
            foreach (self::CHANGES as $table => [$from, $to]) {
                if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'payment_method')) {
                    continue;
                }
                $ids = DB::table($table)->where('payment_method', $from)->pluck('id')->all();
                if ($ids) {
                    foreach (array_chunk($ids, 500) as $chunk) {
                        DB::table($table)->whereIn('id', $chunk)->update(['payment_method' => $to]);
                    }
                }
                $log[$table] = ['from' => $from, 'to' => $to, 'ids' => $ids];
            }
        });

        Storage::put(self::LOG, json_encode(['ran_at' => now()->toDateTimeString(), 'changes' => $log], JSON_PRETTY_PRINT));
    }

    public function down(): void
    {
        if (!Storage::exists(self::LOG)) {
            return;
        }
        $log = json_decode(Storage::get(self::LOG), true);

        DB::transaction(function () use ($log) {
            foreach ($log['changes'] ?? [] as $table => $c) {
                foreach (array_chunk($c['ids'] ?? [], 500) as $chunk) {
                    DB::table($table)->whereIn('id', $chunk)->where('payment_method', $c['to'])->update(['payment_method' => $c['from']]);
                }
            }
        });
    }
};
