<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Player;
use App\Models\TrainingSession;
use App\Models\Attendance;
use App\Models\Matchs;  // ← Changé de Match à Matchs
use App\Models\Notification;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Admin
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@club.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '0600000001',
            'is_active' => true,
        ]);

        // Create Coaches
        $coach1 = User::create([
            'name' => 'Coach Mohamed',
            'email' => 'coach1@club.com',
            'password' => Hash::make('password'),
            'role' => 'coach',
            'phone' => '0600000002',
            'is_active' => true,
        ]);

        $coach2 = User::create([
            'name' => 'Coach Ahmed',
            'email' => 'coach2@club.com',
            'password' => Hash::make('password'),
            'role' => 'coach',
            'phone' => '0600000003',
            'is_active' => true,
        ]);

        // Create Players
        $playerNames = [
            ['Yassine', 'Bounou', 'goalkeeper'],
            ['Achraf', 'Hakimi', 'defender'],
            ['Noussair', 'Mazraoui', 'defender'],
            ['Romain', 'Saiss', 'defender'],
            ['Sofyan', 'Amrabat', 'midfielder'],
            ['Azzedine', 'Ounahi', 'midfielder'],
            ['Hakim', 'Ziyech', 'midfielder'],
            ['Youssef', 'En-Nesyri', 'forward'],
            ['Zakaria', 'Aboukhlal', 'forward'],
            ['Ilias', 'Chair', 'forward'],
        ];

        $players = [];
        foreach ($playerNames as $index => $playerData) {
            $user = User::create([
                'name' => $playerData[0] . ' ' . $playerData[1],
                'email' => strtolower($playerData[0]) . '@club.com',
                'password' => Hash::make('password'),
                'role' => 'player',
                'phone' => '06' . str_pad($index + 10, 8, '0', STR_PAD_LEFT),
                'is_active' => true,
            ]);

            $player = Player::create([
                'user_id' => $user->id,
                'first_name' => $playerData[0],
                'last_name' => $playerData[1],
                'date_of_birth' => now()->subYears(rand(20, 30)),
                'position' => $playerData[2],
                'jersey_number' => $index + 1,
                'status' => 'active',
            ]);

            $players[] = $player;
        }

        // Create Training Sessions
        for ($i = 0; $i < 10; $i++) {
            $training = TrainingSession::create([
                'coach_id' => rand(0, 1) ? $coach1->id : $coach2->id,
                'title' => 'Training Session ' . ($i + 1),
                'description' => 'Regular training focusing on fitness and tactics',
                'date' => now()->subDays(rand(1, 30)),
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'location' => 'Main Stadium',
                'status' => $i < 7 ? 'completed' : 'scheduled',
            ]);

            // Create attendances for each player
            foreach ($players as $player) {
                Attendance::create([
                    'training_session_id' => $training->id,
                    'player_id' => $player->id,
                    'status' => rand(0, 10) > 2 ? 'present' : 'absent',
                    'performance_score' => $training->status === 'completed' ? rand(5, 10) : null,
                    'remarks' => rand(0, 10) > 8 ? 'Good performance' : null,
                ]);
            }
        }

        // Create Matches
        $opponents = ['FC Barcelona', 'Real Madrid', 'Manchester City', 'Bayern Munich', 'Paris SG'];
        
        foreach ($opponents as $index => $opponent) {
            Matchs::create([  // ← Changé de Match à Matchs
                'opponent_team' => $opponent,
                'match_date' => now()->subDays(rand(1, 60)),
                'match_time' => '20:00:00',
                'location' => 'Home Stadium',
                'match_type' => 'league',
                'our_score' => rand(0, 4),
                'opponent_score' => rand(0, 3),
                'result' => ['win', 'loss', 'draw'][rand(0, 2)],
                'status' => 'completed',
            ]);
        }

        // Upcoming matches
        Matchs::create([  // ← Changé de Match à Matchs
            'opponent_team' => 'Liverpool FC',
            'match_date' => now()->addDays(7),
            'match_time' => '20:00:00',
            'location' => 'Home Stadium',
            'match_type' => 'cup',
            'status' => 'scheduled',
        ]);

        // Create Notifications
        Notification::create([
            'created_by' => $admin->id,
            'title' => 'Welcome to Club Management System',
            'message' => 'Welcome to the new club management platform. Please check your schedule regularly.',
            'type' => 'info',
            'target_role' => 'all',
            'is_active' => true,
        ]);

        Notification::create([
            'created_by' => $coach1->id,
            'title' => 'Training Tomorrow',
            'message' => 'Remember, we have an important training session tomorrow at 10 AM.',
            'type' => 'warning',
            'target_role' => 'player',
            'is_active' => true,
        ]);

        Notification::create([
            'created_by' => $admin->id,
            'title' => 'Match This Weekend',
            'message' => 'Important match against Liverpool FC this weekend. Be prepared!',
            'type' => 'urgent',
            'target_role' => 'all',
            'is_active' => true,
        ]);

        echo "✅ Database seeded successfully!\n";
        echo "📧 Admin: admin@club.com\n";
        echo "📧 Coach: coach1@club.com\n";
        echo "📧 Player: yassine@club.com\n";
        echo "🔐 Password for all: password\n";
    }
}