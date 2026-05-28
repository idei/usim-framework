<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usim_permission_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->cascadeOnDelete(); // One-to-one FK to Spatie's permissions table
            $table->string('display_name')
                ->nullable(); // Optional human-friendly name or i18n key for display purposes
            $table->string('description')
                ->nullable(); // Optional description or i18n key for display purposes
            $table->json('metadata')
                ->nullable(); // For any future extensibility needs
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usim_permission_settings');
    }
};
