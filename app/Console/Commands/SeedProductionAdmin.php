<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Production-safe admin bootstrap — see MASTER_SPECIFICATION.md §7.1's
 * seed_production_admin.md and §10.4 §8's explicit warning: the DEV/staging
 * seed path uses a well-known placeholder password hash and must never be
 * the way a production admin account is created. This command generates a
 * real random password instead, prints it exactly once, and never writes
 * it to storage/logs.
 *
 * Deliberately NOT part of `php artisan migrate --seed` or DatabaseSeeder —
 * must be run explicitly, once, by a deployer.
 */
class SeedProductionAdmin extends Command
{
    protected $signature = 'app:seed-production-admin
        {--name=System Administrator}
        {--email=admin@example.com}';

    protected $description = 'Create the production bootstrap Administrator account with a random password (never the dev placeholder hash).';

    public function handle(): int
    {
        $email = $this->option('email');

        if (User::where('email', $email)->whereNull('deleted_at')->exists()) {
            $this->components->error("An active user with email [{$email}] already exists — refusing to create a duplicate admin.");

            return self::FAILURE;
        }

        $administratorRole = Role::where('slug', 'administrator')->first();

        if (! $administratorRole) {
            $this->components->error('The administrator role does not exist yet — run the RolesAndPermissionsSeeder first.');

            return self::FAILURE;
        }

        $password = Str::password(24);

        $user = User::create([
            'name' => $this->option('name'),
            'email' => $email,
            'password_hash' => Hash::make($password),
            'status' => 'active',
        ]);

        $user->roles()->attach($administratorRole->id, ['is_primary' => true]);

        $this->components->info('Production admin account created.');
        $this->line('');
        $this->line("  Email:    {$email}");
        $this->line("  Password: {$password}");
        $this->line('');
        $this->components->warn('Deliver this password out-of-band and require a change on first login. It will not be shown again and is not written to any log.');

        return self::SUCCESS;
    }
}
