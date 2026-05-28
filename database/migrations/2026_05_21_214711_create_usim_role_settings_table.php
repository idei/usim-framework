<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('usim_role_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete(); // FK to Spatie's roles table
            $table->string('home_screen')
                ->nullable(); // Store the class name of the home screen
            $table->integer('priority')
                ->default(100); // For determine role precedence if needed
            $table->string('display_name')
                ->nullable(); // Optional human-friendly name or i18n key for display purposes
            $table->string('description')
                ->nullable(); // Optional description or i18n key for display purposes
            $table->json('metadata')
                ->nullable(); // For any future extensibility needs
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usim_role_settings');
    }
};
