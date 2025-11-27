<?php
// database/migrations/2024_01_01_000005_create_matches_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matchs', function (Blueprint $table) {
            $table->id();
            $table->string('opponent_team');
            $table->date('match_date');
            $table->time('match_time');
            $table->string('location');
            $table->enum('match_type', ['friendly', 'league', 'cup', 'tournament'])->default('friendly');
            $table->integer('our_score')->nullable();
            $table->integer('opponent_score')->nullable();
            $table->enum('result', ['win', 'loss', 'draw', 'pending'])->default('pending');
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};