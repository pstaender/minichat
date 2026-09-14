<?php

namespace Philipp\Minichat;

/**
 * Reads chat_config.json (the list of users allowed to log in, their
 * provider/model choice, API key and token limits).
 */
class Config
{
    /** @var array<int, array<string, mixed>> */
    private array $users;

    public function __construct(string $path)
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Config file not found: $path");
        }

        $raw = file_get_contents($path);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new \RuntimeException("chat_config.json must be a JSON array of users");
        }

        $this->users = array_map([$this, 'normalize'], array_values($data));
    }

    private function normalize(array $u): array
    {
        $u['email'] = $u['email'] ?? null;
        $u['id'] = (string) ($u['id'] ?? $u['email'] ?? '');

        $models = $u['model'] ?? [];
        $u['model'] = is_array($models) ? array_values(array_filter($models)) : array_filter([$models]);

        $u['daily_token_limit'] = isset($u['daily_token_limit']) ? (int) $u['daily_token_limit'] : null;

        // "daily_token_limit_outout" kept for backwards compatibility with the typo in older configs.
        $outputLimit = $u['daily_token_limit_output'] ?? $u['daily_token_limit_outout'] ?? null;
        $u['daily_token_limit_output'] = $outputLimit !== null ? (int) $outputLimit : null;

        return $u;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->users;
    }

    public function findById(string $id): ?array
    {
        foreach ($this->users as $u) {
            if ($u['id'] === $id) {
                return $u;
            }
        }
        return null;
    }

    /**
     * Looks up a user by password, optionally narrowed by email.
     * Email is optional on login; when omitted, the password alone must match.
     */
    public function findByCredentials(?string $email, string $password): ?array
    {
        $email = $email !== null ? trim($email) : null;

        foreach ($this->users as $u) {
            if (!hash_equals((string) $u['password'], $password)) {
                continue;
            }

            if ($email !== null && $email !== '') {
                if (strcasecmp((string) $u['email'], $email) === 0) {
                    return $u;
                }
                continue;
            }

            return $u;
        }

        return null;
    }

    public function publicUser(array $u): array
    {
        return [
            'id' => $u['id'],
            'email' => $u['email'],
            'provider' => $u['provider'],
            'model' => array_values($u['model']),
            'daily_token_limit' => $u['daily_token_limit'],
            'daily_token_limit_output' => $u['daily_token_limit_output'],
        ];
    }
}
