<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;
use PDO;

class UserGenreRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT genre_id FROM user_genre WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function replace(int $userId, array $genreIds): void
    {
        $genreIds = array_values(array_unique(array_filter(
            array_map('intval', $genreIds),
            static fn (int $id): bool => $id > 0
        )));

        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM user_genre WHERE user_id = :user_id');
            $delete->execute(['user_id' => $userId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO user_genre (user_id, genre_id) VALUES (:user_id, :genre_id)'
            );

            foreach ($genreIds as $genreId) {
                $insert->execute(['user_id' => $userId, 'genre_id' => $genreId]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function findAllIds(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT tmdb_id FROM user_movies WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

