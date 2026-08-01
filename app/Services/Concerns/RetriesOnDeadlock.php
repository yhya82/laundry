<?php

namespace App\Services\Concerns;

use Illuminate\Database\QueryException;

/**
 * Shared by every service that takes one of the two named SELECT ... FOR
 * UPDATE boundaries (Database_Design_Document §6: store-credit writes,
 * collection-to-order conversion) — MASTER_SPECIFICATION.md §8 flags the
 * application-layer retry-on-1213 pattern as never having existed; both
 * boundaries use this one implementation rather than inventing it twice.
 */
trait RetriesOnDeadlock
{
    private function retryOnDeadlock(callable $callback, int $maxAttempts = 3)
    {
        $attempt = 0;

        while (true) {
            try {
                return $callback();
            } catch (QueryException $e) {
                $attempt++;
                $mysqlErrorCode = $e->errorInfo[1] ?? null;
                $isTransient = in_array($mysqlErrorCode, [1213, 1205], true); // deadlock, lock wait timeout

                if (! $isTransient || $attempt >= $maxAttempts) {
                    throw $e;
                }

                usleep(50_000 * $attempt);
            }
        }
    }
}
