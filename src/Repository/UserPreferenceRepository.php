<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;
use PDO;

class UserPreferenceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT preferred_mood, recommendation_intensity, profile_signature
             FROM user_preferences WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $preferences = $stmt->fetch(PDO::FETCH_ASSOC);

        return $preferences ?: [
            'preferred_mood' => 'evader',
            'recommendation_intensity' => 'equilibree',
            'profile_signature' => '',
        ];
    }

    public function save(int $userId, string $mood, string $intensity, string $signature): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_preferences
                (user_id, preferred_mood, recommendation_intensity, profile_signature)
             VALUES (:user_id, :mood, :intensity, :signature)
             ON DUPLICATE KEY UPDATE
                preferred_mood = VALUES(preferred_mood),
                recommendation_intensity = VALUES(recommendation_intensity),
                profile_signature = VALUES(profile_signature),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'user_id' => $userId,
            'mood' => $mood,
            'intensity' => $intensity,
            'signature' => $signature,
        ]);
    }
}
