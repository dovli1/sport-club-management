<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Player;
use App\Models\TrainingSession;
use App\Models\Attendance;
use App\Models\Matchs;
use App\Models\Notification;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ 1. Create Admin
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@club.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'team' => null, // Admin n'a pas d'équipe spécifique
            'phone' => '0600000001',
            'is_active' => true,
        ]);

        // ✅ 2. Create Coaches avec équipes assignées
        $coaches = [];
        $coachNames = [
            ['Mohamed Bennani', 'U18 Masculin'],
            ['Ahmed Ziani', 'Seniors Masculin'],
            ['Fatima Alaoui', 'Seniors Féminin'],
            ['Samira El Amrani', 'U18 Féminin'],
            ['Youssef Kadiri', 'U15 Masculin'],
            ['Latifa Bouchaib', 'U15 Féminin'],
        ];

        foreach ($coachNames as $index => $coachData) {
            $coaches[] = User::create([
                'name' => $coachData[0],
                'email' => 'coach' . ($index + 1) . '@club.com',
                'password' => Hash::make('password'),
                'role' => 'coach',
                'team' => $coachData[1], // ✅ Équipe assignée
                'phone' => '060000000' . ($index + 2),
                'is_active' => true,
            ]);
        }

        // ✅ 3. Create Players répartis dans les équipes
        $playersByTeam = [
            'U18 Masculin' => [
                ['Youssef', 'Benali', 'Passeur', 17],
                ['Amine', 'El Idrissi', 'Réceptionneur-Attaquant', 18],
                ['Karim', 'Fassi', 'Central', 17],
                ['Rachid', 'Bouchaib', 'Central', 18],
                ['Sami', 'El Amrani', 'Réceptionneur-Attaquant', 17],
            ],
            'Seniors Masculin' => [
                ['Adil', 'Ouhbi', 'Opposé', 24],
                ['Hakim', 'Ziyad', 'Opposé', 26],
                ['Yassir', 'El Khattabi', 'Libéro', 25],
                ['Nabil', 'Bouazizi', 'Réceptionneur-Attaquant', 23],
                ['Imad', 'Cherif', 'Passeur', 27],
            ],
            'Seniors Féminin' => [
                ['Fatima', 'Zahra', 'Passeur', 22],
                ['Meryem', 'Alami', 'Central', 24],
                ['Khadija', 'Bennani', 'Libéro', 23],
                ['Sanaa', 'Idrissi', 'Attaquant', 25],
                ['Nadia', 'Fassi', 'Opposé', 26],
            ],
            'U18 Féminin' => [
                ['Amal', 'Tazi', 'Passeur', 17],
                ['Salma', 'Kadiri', 'Central', 18],
                ['Imane', 'Berrada', 'Libéro', 17],
                ['Houda', 'Chraibi', 'Attaquant', 18],
            ],
            'U15 Masculin' => [
                ['Omar', 'Alaoui', 'Passeur', 14],
                ['Anas', 'Benchekroun', 'Central', 15],
                ['Mehdi', 'Lahlou', 'Libéro', 14],
                ['Zakaria', 'Tounsi', 'Attaquant', 15],
            ],
            'U15 Féminin' => [
                ['Yasmine', 'Idrissi', 'Passeur', 14],
                ['Sara', 'Benmoussa', 'Central', 15],
                ['Hajar', 'Tazi', 'Libéro', 14],
                ['Amina', 'Berrada', 'Attaquant', 15],
            ],
        ];

        $players = [];
        $playerIndex = 1;

        foreach ($playersByTeam as $team => $teamPlayers) {
            foreach ($teamPlayers as $playerData) {
                $firstName = $playerData[0];
                $lastName = $playerData[1];
                $position = $playerData[2];
                $age = $playerData[3];

                $user = User::create([
                    'name' => $firstName . ' ' . $lastName,
                    'email' => strtolower($firstName) . '@club.com',
                    'password' => Hash::make('password'),
                    'role' => 'player',
                    'phone' => '06' . str_pad($playerIndex + 10, 8, '0', STR_PAD_LEFT),
                    'is_active' => true,
                ]);

                $player = Player::create([
                    'user_id' => $user->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'date_of_birth' => now()->subYears($age),
                    'position' => $position,
                    'jersey_number' => $playerIndex,
                    'team' => $team, // ✅ Équipe assignée
                    'status' => 'active',
                ]);

                $players[$team][] = $player;
                $playerIndex++;
            }
        }

        // ✅ 4. Create Training Sessions (par équipe)
        foreach ($coaches as $coach) {
            if (!$coach->team) continue;

            for ($i = 0; $i < 5; $i++) {
                $training = TrainingSession::create([
                    'coach_id' => $coach->id,
                    'title' => 'Entraînement ' . $coach->team . ' #' . ($i + 1),
                    'description' => 'Entraînement régulier - Technique et tactique',
                    'date' => now()->subDays(rand(1, 30)),
                    'start_time' => '10:00:00',
                    'end_time' => '12:00:00',
                    'location' => 'Gymnase Principal',
                    'status' => $i < 3 ? 'completed' : 'scheduled',
                ]);

                // Créer les présences uniquement pour les joueurs de cette équipe
                if (isset($players[$coach->team])) {
                    foreach ($players[$coach->team] as $player) {
                        Attendance::create([
                            'training_session_id' => $training->id,
                            'player_id' => $player->id,
                            'status' => rand(0, 10) > 2 ? 'present' : 'absent',
                            'performance_score' => $training->status === 'completed' ? rand(5, 10) : null,
                            'remarks' => rand(0, 10) > 8 ? 'Bonne performance' : null,
                        ]);
                    }
                }
            }
        }

        // ✅ 5. Create Matches
        $opponents = [
            'Paris Volley',
            'Lyon Volley',
            'Marseille VB',
            'Toulouse Volley',
            'Bordeaux VB'
        ];
        
        foreach ($opponents as $index => $opponent) {
            Matchs::create([
                'opponent_team' => $opponent,
                'match_date' => now()->subDays(rand(1, 60)),
                'match_time' => '20:00:00',
                'location' => 'Stade Principal',
                'match_type' => 'league',
                'our_score' => rand(0, 4),
                'opponent_score' => rand(0, 3),
                'result' => ['win', 'loss', 'draw'][rand(0, 2)],
                'status' => 'completed',
            ]);
        }

        // Upcoming matches
        Matchs::create([
            'opponent_team' => 'Lille Volley',
            'match_date' => now()->addDays(7),
            'match_time' => '20:00:00',
            'location' => 'Stade Principal',
            'match_type' => 'cup',
            'status' => 'scheduled',
        ]);

        // ✅ 6. Create Notifications
        Notification::create([
            'created_by' => $admin->id,
            'title' => 'Bienvenue sur la plateforme',
            'message' => 'Bienvenue sur la nouvelle plateforme de gestion du club. Consultez régulièrement votre planning.',
            'type' => 'info',
            'target_role' => 'all',
            'is_active' => true,
        ]);

        Notification::create([
            'created_by' => $coaches[0]->id,
            'title' => 'Entraînement demain',
            'message' => 'N\'oubliez pas l\'entraînement important demain à 10h.',
            'type' => 'warning',
            'target_role' => 'player',
            'is_active' => true,
        ]);

        Notification::create([
            'created_by' => $admin->id,
            'title' => 'Match ce weekend',
            'message' => 'Match important contre Lille Volley ce weekend. Soyez prêts !',
            'type' => 'urgent',
            'target_role' => 'all',
            'is_active' => true,
        ]);

        // ✅ Affichage des credentials
        echo "\n✅ Database seeded successfully!\n\n";
        echo "═══════════════════════════════════════\n";
        echo "📧 ADMIN\n";
        echo "   Email: admin@club.com\n";
        echo "   Password: password\n";
        echo "═══════════════════════════════════════\n";
        echo "📧 COACHES\n";
        foreach ($coaches as $index => $coach) {
            echo "   Coach " . ($index + 1) . ": coach" . ($index + 1) . "@club.com (Équipe: {$coach->team})\n";
        }
        echo "═══════════════════════════════════════\n";
        echo "📧 PLAYERS (exemples)\n";
        echo "   youssef@club.com (U18 Masculin)\n";
        echo "   adil@club.com (Seniors Masculin)\n";
        echo "   fatima@club.com (Seniors Féminin)\n";
        echo "   amal@club.com (U18 Féminin)\n";
        echo "   omar@club.com (U15 Masculin)\n";
        echo "   yasmine@club.com (U15 Féminin)\n";
        echo "═══════════════════════════════════════\n";
        echo "🔐 Password for all: password\n\n";
    }
}