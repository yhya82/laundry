<?php

namespace App\Models;

use App\Models\Concerns\IsAppendOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTimelineEvent extends Model
{
    use HasFactory, IsAppendOnly;

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'event_type',
        'reference_table',
        'reference_id',
        'title',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
