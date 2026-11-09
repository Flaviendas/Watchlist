<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;
use PDO;

class UserMovieRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function add(int $userId, int $tmdbId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_movies (user_id, tmdb_id, status, source)
             VALUES (:user_id, :tmdb_id, 'to_watch', 'manual')
             ON DUPLICATE KEY UPDATE
                source = IF(status = 'seen', source, 'manual'),
                status = IF(status = 'seen', status, 'to_watch')"
        );
        $stmt->execute(['user_id' => $userId, 'tmdb_id' => $tmdbId]);
    }

    public function addAutomatic(int $userId, int $tmdbId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO user_movies (user_id, tmdb_id, status, source)
             VALUES (:user_id, :tmdb_id, 'to_watch', 'automatic')"
        );
        $stmt->execute(['user_id' => $userId, 'tmdb_id' => $tmdbId]);
    }

    public function removeAutomaticToWatch(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM user_movies
             WHERE user_id = :user_id AND status = 'to_watch' AND source = 'automatic'"
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public function findByUser(int $userId, string $status): array
    {
        if (!in_array($status, ['to_watch', 'seen'], true)) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT tmdb_id, source FROM user_movies
             WHERE user_id = :user_id AND status = :status
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['user_id' => $userId, 'status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markSeen(int $userId, int $tmdbId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_movies (user_id, tmdb_id, status)
             VALUES (:user_id, :tmdb_id, 'seen')
             ON DUPLICATE KEY UPDATE status = 'seen'"
        );
        $stmt->execute(['user_id' => $userId, 'tmdb_id' => $tmdbId]);
    }

    public function isSeen(int $userId, int $tmdbId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM user_movies
             WHERE user_id = :user_id AND tmdb_id = :tmdb_id AND status = 'seen'"
        );
        $stmt->execute(['user_id' => $userId, 'tmdb_id' => $tmdbId]);
        return (bool) $stmt->fetchColumn();
    }

    public function remove(int $userId, int $tmdbId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM user_movies WHERE user_id = :user_id AND tmdb_id = :tmdb_id'
        );
        $stmt->execute(['user_id' => $userId, 'tmdb_id' => $tmdbId]);
    }

    public function findAllIds(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT tmdb_id FROM user_movies WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
