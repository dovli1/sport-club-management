<?php
// database/migrations/2024_01_01_000004_create_attendances_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('absent');
            $table->integer('performance_score')->nullable(); // Note de 1 à 10
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Eviter les doublons
            $table->unique(['training_session_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};