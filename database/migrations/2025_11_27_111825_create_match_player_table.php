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
        Schema::create('match_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('matchs_id')->constrained('matchs')->onDelete('cascade');
            $table->boolean('is_starter')->default(false);
            $table->integer('minutes_played')->nullable();
            $table->integer('goals')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('yellow_cards')->default(0);
            $table->integer('red_cards')->default(0);
            $table->decimal('rating', 3, 2)->nullable(); // Rating out of 10, e.g., 7.50
            $table->timestamps();

            // Ensure a player can only be in a match once
            $table->unique(['player_id', 'matchs_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_player');
    }
};
