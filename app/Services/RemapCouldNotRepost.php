<?php

namespace App\Services;

use RuntimeException;

/**
 * The replacement posting could not be written, so the reversal must be undone.
 *
 * Thrown and caught entirely inside TransactionAutoMapService::remap(), purely
 * to roll back the transaction the reversal shares. It never escapes that
 * method: a model save that triggered an observer must not blow up because the
 * ledger could not be re-posted, and the caller is told by a false return and
 * by the warning autoMap() has already flashed.
 *
 * It exists as its own class so the catch can name exactly this case rather
 * than swallowing every RuntimeException the posting path might raise.
 */
class RemapCouldNotRepost extends RuntimeException
{
}
