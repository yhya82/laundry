<?php

namespace App\Models;

use App\Models\Concerns\IsAppendOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryStatusHistory extends Model
{
    use HasFactory, IsAppendOnly;

    protected $table = 'delivery_status_history';

    public $timestamps = false;

    protected $fillable = [
        'delivery_id',
        'status',
        'notes',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
