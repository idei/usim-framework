<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('usim_role_settings', function (Blueprint $table) {
            $table->id();
            // Foreign key to Spatie table
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('home_screen')->nullable(); // Store the class name of the home screen
            $table->integer('priority')->default(0); // For future use, to determine role precedence if needed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usim_role_settings');
    }
};
