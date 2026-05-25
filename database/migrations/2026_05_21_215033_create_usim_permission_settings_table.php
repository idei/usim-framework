<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usim_permission_settings', function (Blueprint $table) {
            $table->id();
            // One-to-one relationship with Spatie permissions table
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('description')->nullable(); // Optional description for the permission
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
