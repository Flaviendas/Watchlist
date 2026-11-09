<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function create(string $email, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (:email, :hash)');
        $stmt->execute(['email' => $email, 'hash' => $passwordHash]);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email =:email');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }
}