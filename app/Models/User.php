<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    // Notifiable is kept only for Fortify's mail-channel password-reset
    // notification (Notifiable::notify() routes to mail/etc. regardless of
    // any database table). Its bundled notifications()/HasDatabaseNotifications
    // relationship assumes Laravel's own notifications table shape
    // (notifiable_type/notifiable_id, uuid PK) — this schema's `notifications`
    // table is a deliberately different, custom shape (recipient_type/
    // recipient_id, delivery_status, channel — see MASTER_SPECIFICATION.md
    // §2.1/§10.1). Query the business notification system through
    // App\Models\Notification with recipient_type='user', not $user->notifications.
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password_hash',
        'branch_id',
        'status',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    /**
     * users.password_hash stands in for the stock `password` column —
     * see MASTER_SPECIFICATION.md schema.sql's users table.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // user_roles only carries created_at (DB-defaulted), not updated_at —
    // withTimestamps() is deliberately omitted, same reasoning as Role::users().
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('is_primary', 'assigned_by');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function employee(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function hasPermission(string $slug): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }
}
