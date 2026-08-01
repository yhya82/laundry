<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    // uq_employees_user enforces at most one employee row per user —
    // hasOne, not hasMany (a Phase 2 oversight, fixed in Phase 4).
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * @var array<string>|null
     */
    private ?array $permissionSlugsCache = null;

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->permissionSlugs(), true);
    }

    public function hasAnyPermission(array $slugs): bool
    {
        return count(array_intersect($slugs, $this->permissionSlugs())) > 0;
    }

    /**
     * Union of permission slugs across every active role this user holds —
     * memoized per request/instance so N permission checks (e.g. rendering
     * the sidebar) don't cost N queries.
     *
     * @return array<string>
     */
    public function permissionSlugs(): array
    {
        return $this->permissionSlugsCache ??= $this->roles()
            ->join('role_permissions', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->pluck('permissions.slug')
            ->unique()
            ->values()
            ->all();
    }
}
