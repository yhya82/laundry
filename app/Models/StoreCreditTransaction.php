<?php

namespace App\Models;

use App\Models\Concerns\IsAppendOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreCreditTransaction extends Model
{
    use HasFactory, IsAppendOnly;

    protected $table = 'store_credit_transactions';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'direction',
        'amount',
        'balance_after',
        'source_type',
        'source_table',
        'source_id',
        'reference_note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
