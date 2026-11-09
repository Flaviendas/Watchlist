<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;
use PDO;

class MovieReviewRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $userId, int $tmdbId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT feeling, emotions, intensity, note, genre_ids
             FROM movie_reviews
             WHERE user_id = :user_id AND tmdb_id = :tmdb_id'
        );
        $stmt->execute(['user_id' => $userId, 'tmdb_id' => $tmdbId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            return null;
        }

        $review['emotions'] = json_decode($review['emotions'] ?? '[]', true) ?: [];
        $review['genre_ids'] = json_decode($review['genre_ids'] ?? '[]', true) ?: [];
        return $review;
    }

    public function findAllByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tmdb_id, feeling, genre_ids
             FROM movie_reviews
             WHERE user_id = :user_id
             ORDER BY updated_at DESC
             LIMIT 30'
        );
        $stmt->execute(['user_id' => $userId]);

        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reviews as &$review) {
            $review['tmdb_id'] = (int) $review['tmdb_id'];
            $review['genre_ids'] = array_map(
                'intval',
                json_decode($review['genre_ids'] ?? '[]', true) ?: []
            );
        }
        unset($review);

        return $reviews;
    }

    public function save(
        int $userId,
        int $tmdbId,
        string $feeling,
        array $emotions,
        ?string $intensity,
        string $note,
        array $genreIds
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO movie_reviews
                (user_id, tmdb_id, feeling, emotions, intensity, note, genre_ids)
             VALUES
                (:user_id, :tmdb_id, :feeling, :emotions, :intensity, :note, :genre_ids)
             ON DUPLICATE KEY UPDATE
                feeling = VALUES(feeling),
                emotions = VALUES(emotions),
                intensity = VALUES(intensity),
                note = VALUES(note),
                genre_ids = VALUES(genre_ids),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'user_id' => $userId,
            'tmdb_id' => $tmdbId,
            'feeling' => $feeling,
            'emotions' => json_encode(array_values($emotions), JSON_UNESCAPED_UNICODE),
            'intensity' => $intensity,
            'note' => $note,
            'genre_ids' => json_encode(array_values(array_map('intval', $genreIds))),
        ]);
    }
}

