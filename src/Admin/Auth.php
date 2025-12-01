<?php

declare(strict_types=1);

namespace MeterN\Admin;

/**
 * Credential manager using password_hash() with atomic file writes and 0600 permissions.
 *
 * Replaces the legacy .htpasswd + crypt() approach with secure password hashing
 * and proper file security.
 */
class Auth
{
    private string $credentialsFile;

    /**
     * @param string $credentialsFile Path to the credentials JSON file
     */
    public function __construct(string $credentialsFile)
    {
        $this->credentialsFile = $credentialsFile;
    }

    /**
     * Create a new user with securely hashed password.
     *
     * Uses atomic file writes (write to temp, then rename) to prevent corruption.
     * Sets file permissions to 0600 (owner read/write only).
     *
     * @param string $username The username to create
     * @param string $password The plain-text password to hash
     * @return bool True on success, false on failure
     */
    public function createUser(string $username, string $password): bool
    {
        $username = trim($username);
        $password = trim($password);

        if ($username === '' || $password === '') {
            return false;
        }

        $users = $this->loadUsers();

        // Hash password using bcrypt (default algorithm in PHP 7.4+)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        if ($hashedPassword === false) {
            return false;
        }

        $users[$username] = [
            'password' => $hashedPassword,
            'created_at' => time(),
        ];

        return $this->saveUsers($users);
    }

    /**
     * Verify username and password against stored credentials.
     *
     * @param string $username The username to verify
     * @param string $password The plain-text password to verify
     * @return bool True if credentials match, false otherwise
     */
    public function verify(string $username, string $password): bool
    {
        $username = trim($username);
        $password = trim($password);

        if ($username === '' || $password === '') {
            return false;
        }

        $users = $this->loadUsers();

        if (!isset($users[$username]['password'])) {
            return false;
        }

        return password_verify($password, $users[$username]['password']);
    }

    /**
     * Check if any users exist in the credentials file.
     *
     * @return bool True if at least one user exists
     */
    public function hasUsers(): bool
    {
        $users = $this->loadUsers();
        return count($users) > 0;
    }

    /**
     * Load users from the credentials file.
     *
     * @return array<string, array{password: string, created_at: int}>
     */
    private function loadUsers(): array
    {
        if (!file_exists($this->credentialsFile)) {
            return [];
        }

        $content = file_get_contents($this->credentialsFile);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * Save users to the credentials file atomically.
     *
     * Writes to a temporary file first, then renames to prevent corruption.
     * Sets file permissions to 0600 for security.
     *
     * @param array<string, array{password: string, created_at: int}> $users
     * @return bool True on success
     */
    private function saveUsers(array $users): bool
    {
        $dir = dirname($this->credentialsFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        $tempFile = $this->credentialsFile . '.tmp.' . getmypid();
        $json = json_encode($users, JSON_PRETTY_PRINT);

        if ($json === false) {
            return false;
        }

        // Write to temp file first
        if (file_put_contents($tempFile, $json, LOCK_EX) === false) {
            return false;
        }

        // Set secure permissions before rename
        if (!chmod($tempFile, 0600)) {
            unlink($tempFile);
            return false;
        }

        // Atomic rename
        if (!rename($tempFile, $this->credentialsFile)) {
            unlink($tempFile);
            return false;
        }

        return true;
    }
}
