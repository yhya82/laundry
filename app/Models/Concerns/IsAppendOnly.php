<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * For tables permissions.sql revokes UPDATE/DELETE on entirely (BR-060,
 * BR-063) — activity_log, delivery_status_history, store_credit_transactions,
 * customer_timeline_events. The database already refuses these under the
 * `laundry_app` grant; this makes the same intent visible and enforced at
 * the model layer too, so a bug can't even construct the query.
 */
trait IsAppendOnly
{
    public function delete(): bool
    {
        throw new LogicException(static::class.' is append-only — records cannot be deleted.');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException(static::class.' is append-only — records cannot be updated.');
        }

        return parent::save($options);
    }
}
