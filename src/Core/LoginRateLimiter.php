<?php

class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function isLocked(string $email): bool
    {
        $attempts = $this->getRecentAttempts($email, self::LOCKOUT_MINUTES);
        return $attempts >= self::MAX_ATTEMPTS;
    }

    public function recordFailedAttempt(string $email): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $this->db->prepare(
            "INSERT INTO mova_login_attempts (email, ip_address, is_success) VALUES (?, ?, 0)"
        );
        $stmt->execute([$email, $ip]);
    }

    public function recordSuccessfulAttempt(string $email): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $this->db->prepare(
            "INSERT INTO mova_login_attempts (email, ip_address, is_success) VALUES (?, ?, 1)"
        );
        $stmt->execute([$email, $ip]);
    }

    public function clearAttempts(string $email): void
    {
        $stmt = $this->db->prepare("DELETE FROM mova_login_attempts WHERE email = ?");
        $stmt->execute([$email]);
    }

    private function getRecentAttempts(string $email, int $minutes): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM mova_login_attempts
             WHERE email = ? AND is_success = 0
             AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$email, $minutes]);
        return (int) $stmt->fetchColumn();
    }
}
