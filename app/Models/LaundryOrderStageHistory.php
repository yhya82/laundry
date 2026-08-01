<?php

namespace App\Models;

use App\Models\Concerns\ForbidsDelete;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resolves the append-only-vs-nullable-completed_at tension between
 * MASTER_SPECIFICATION.md's permissions.sql and this table's column design
 * (see 2026_07_31_100220_create_stage_history_tamper_trigger.php for the
 * full reasoning): treated as an interval log, same shape as Payment/Refund/
 * Receipt/DamageReport — DELETE forbidden, UPDATE legitimate but restricted
 * to setting completed_at exactly once. trg_losh_prevent_tamper enforces
 * this at the database level; ForbidsDelete enforces the delete side here.
 */
class LaundryOrderStageHistory extends Model
{
    use ForbidsDelete, HasFactory;

    protected $table = 'laundry_order_stage_history';

    public $timestamps = false;

    protected $fillable = [
        'laundry_order_id',
        'stage',
        'machine_id',
        'started_at',
        'completed_at',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function laundryOrder(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
