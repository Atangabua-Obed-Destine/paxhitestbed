<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Accounting\LedgerHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The ledger health check, on a screen.
 *
 * accounting:ledger-doctor does the same work from the command line, and that
 * is the better tool when there is a terminal. There is usually not: these
 * institutions run on shared hosting, and the person who needs the answer is
 * the accountant rather than whoever has SSH. A check nobody can run is a
 * check nobody runs.
 *
 * Both read the same LedgerHealth service, so the screen and the command can
 * never give different answers about the same books.
 *
 * Read-only, by construction — LedgerHealth only queries, and every repair it
 * suggests belongs to a command that owns it.
 */
class LedgerHealthController extends Controller
{
    public function __construct()
    {
        /*
         * Guarded by general-ledger-view rather than a permission of its own.
         *
         * This reports on the ledger and shows nothing that reading the ledger
         * would not already show, so anyone who may read it may read its
         * health. It also means the screen works on an existing installation
         * without seeding a new permission first — which matters when the
         * reason for building it was that running anything on production is
         * awkward.
         */
        $this->middleware('permission:general-ledger-view');
    }

    public function index(Request $request, LedgerHealth $health)
    {
        $checks = $health->checks();

        // One group at a time, for a screen that is long when everything is
        // interesting and very long when nothing is.
        if ($group = $request->input('group')) {
            $filtered = array_values(array_filter($checks,
                fn ($c) => strcasecmp($c['group'], $group) === 0));

            if ($filtered !== []) {
                $checks = $filtered;
            }
        }

        return view('admin.accounting.ledger-health.index', [
            'title' => __('Ledger health'),
            'checks' => $checks,
            'findings' => array_values(array_filter($checks, fn ($c) => $c['state'] !== LedgerHealth::OK)),
            'passing' => array_values(array_filter($checks, fn ($c) => $c['state'] === LedgerHealth::OK)),
            'worst' => $health->worstState($checks),
            'concerns' => $health->concerns($checks),
            'groups' => ['Mappings', 'Entries', 'Agreement', 'Placement'],
            'group' => $group,
            'scale' => [
                'mappings' => DB::table('transaction_mappings')->where('status', 'active')->count(),
                'entries' => DB::table('journal_entries')->whereNull('deleted_at')->count(),
                'live' => DB::table('journal_entries')->whereNull('deleted_at')->where('is_reversed', 0)->count(),
            ],
            'checkedAt' => now(),
        ]);
    }
}
