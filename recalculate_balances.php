<?php
/**
 * Script to recalculate all account balances from posted journal entries
 * Run this with: php recalculate_balances.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;

echo "Recalculating Account Balances...\n\n";

// Step 1: Reset all current balances to opening balances
$accounts = ChartOfAccount::all();
foreach ($accounts as $account) {
    $account->current_balance = $account->opening_balance;
    $account->save();
    echo "Reset {$account->account_code} - {$account->account_name} to opening balance: {$account->opening_balance}\n";
}

echo "\n--- Applying Posted Journal Entries ---\n\n";

// Step 2: Reapply all posted journal entries
$postedEntries = JournalEntry::where('is_posted', true)
    ->whereNull('deleted_at')
    ->with('lines.account')
    ->orderBy('posted_at')
    ->get();

foreach ($postedEntries as $entry) {
    echo "Processing {$entry->entry_number} (Posted: {$entry->posted_at})...\n";
    
    foreach ($entry->lines as $line) {
        $account = $line->account;
        $oldBalance = $account->current_balance;
        
        if ($account->normal_balance === 'debit') {
            $account->current_balance += ($line->debit - $line->credit);
        } else {
            $account->current_balance += ($line->credit - $line->debit);
        }
        
        $account->save();
        
        echo "  Account {$account->account_code}: {$oldBalance} => {$account->current_balance} (Debit: {$line->debit}, Credit: {$line->credit})\n";
    }
}

echo "\n--- Final Account Balances ---\n\n";

// Step 3: Display final balances
$accounts = ChartOfAccount::where('current_balance', '<>', 0)->orderBy('account_code')->get();
foreach ($accounts as $account) {
    $formatted_balance = number_format($account->current_balance, 2);
    echo "{$account->account_code} - {$account->account_name}: {$formatted_balance} ({$account->normal_balance})\n";
}

echo "\nDone! Account balances have been recalculated.\n";
