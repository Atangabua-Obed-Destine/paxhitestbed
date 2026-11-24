<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * The school's default ledger account for each kind of payment method, used
 * when a transaction does not name the cash box, bank or wallet it went
 * through. See App\Services\Accounting\CashAccounts.
 */
class LedgerCashSetting extends Model
{
    use Auditable;

    protected $fillable = [
        'cash_account_id', 'bank_account_id', 'mobile_money_account_id', 'other_account_id', 'updated_by',
    ];

    /** The one settings row, created on demand so no screen fails on a fresh install. */
    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }

    public function accountFor(string $kind): ?int
    {
        $id = match ($kind) {
            'cash' => $this->cash_account_id,
            'bank' => $this->bank_account_id,
            'mobile_money' => $this->mobile_money_account_id,
            'other' => $this->other_account_id,
            default => null,
        };

        return $id ? (int) $id : null;
    }
}
