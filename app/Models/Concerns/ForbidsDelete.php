<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * For tables permissions.sql revokes only DELETE on — payments, refunds,
 * receipts, damage_reports. UPDATE stays legitimate for status transitions
 * (e.g. payment 'paid' -> 'refunded'); those transitions are further
 * constrained at the row/column level by triggers.sql, not blocked here.
 */
trait ForbidsDelete
{
    public function delete(): bool
    {
        throw new LogicException(static::class.' records cannot be deleted — use a status transition (e.g. cancel/refund) instead.');
    }
}
