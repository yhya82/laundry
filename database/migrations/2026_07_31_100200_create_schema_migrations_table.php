<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately minimal — not a substitute for Laravel's own
        // migrations table, which already answers "what version is this
        // database on" for anything built through this repo's migration
        // set. Kept only for parity with MASTER_SPECIFICATION.md §10.1,
        // whose schema_migrations table this repo's own `migrations` table
        // has effectively superseded.
        Schema::create('schema_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('version', 50);
            $table->string('description')->nullable();
            $table->dateTime('applied_at')->useCurrent();
            $table->string('applied_by', 100)->nullable();

            $table->unique('version', 'uq_schema_migrations_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_migrations');
    }
};
