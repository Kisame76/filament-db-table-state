<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('db-table-state.table', 'table_states');
        $userColumn = config('db-table-state.user_column', 'user_id');

        Schema::create($tableName, function (Blueprint $table) use ($userColumn): void {
            $table->id();

            // Foreign key to the users table with cascade delete, so a user's
            // saved table state is cleaned up automatically when they are removed.
            // Using UUID/ULID user keys? Swap this line for:
            //     $table->foreignUuid($userColumn)->constrained()->cascadeOnDelete();
            // Non-standard users table or key? Adjust ->constrained('your_table').
            $table->foreignId($userColumn)->constrained()->cascadeOnDelete();
            $table->string('table_key');
            $table->json('state')->nullable();
            $table->timestamps();

            $table->unique([$userColumn, 'table_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('db-table-state.table', 'table_states'));
    }
};
