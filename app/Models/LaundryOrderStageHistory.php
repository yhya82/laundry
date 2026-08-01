<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NOTE — open discrepancy in MASTER_SPECIFICATION.md worth resolving before
 * Phase 6 builds the stage-advance service: permissions.sql revokes UPDATE
 * entirely on this table ("pure append-only logs — no UPDATE or DELETE
 * ever"), but the column design here includes a nullable `completed_at`
 * that reads as intended to be set after the row's initial insert (when the
 * stage finishes). Those two parts of the spec pull in different
 * directions. Not resolved here — no append-only guard has been added to
 * this model (unlike ActivityLog/DeliveryStatusHistory/etc.) specifically
 * so it doesn't silently break whichever behavior turns out to be intended.
 */
class LaundryOrderStageHistory extends Model
{
    use HasFactory;

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
