<?php

namespace Philipp\Minichat;

/**
 * Share codes for chats, in storage/shares.json: each code points to one
 * owner's session and remembers which logged-in users joined through it.
 * A session has at most one live code — generating a new one revokes the
 * old code and everyone who joined with it.
 */
class ShareStore
{
    // Uppercase letters and digits without look-alikes (0/O, 1/I/L, 2/Z, 5/S, 8/B).
    private const ALPHABET = 'ACDEFGHJKMNPQRTUVWXY34679';
    private const LENGTH = 6;

    public function __construct(private string $path)
    {
    }

    /** "ab3-c2d" / "AB3 C2D" → "AB3C2D" */
    public static function normalize(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    }

    /** Creates a fresh code for the session, replacing any previous one. */
    public function create(string $userId, string $sessionId): string
    {
        $code = '';

        Json::update($this->path, function ($data) use ($userId, $sessionId, &$code) {
            $codes = self::withoutSession($data['codes'] ?? [], $userId, $sessionId);
            do {
                $code = '';
                for ($i = 0; $i < self::LENGTH; $i++) {
                    $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                }
            } while (isset($codes[$code]));

            $codes[$code] = [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'created_at' => gmdate('c'),
                'members' => [],
            ];
            $data['codes'] = $codes;
            return $data;
        }, ['codes' => []]);

        return $code;
    }

    public function find(string $code): ?array
    {
        $code = self::normalize($code);
        $share = Json::read($this->path, ['codes' => []])['codes'][$code] ?? null;
        return $share ? ['code' => $code] + $share : null;
    }

    /** Adds a logged-in user to the share's members; null if the code is unknown. */
    public function join(string $code, string $userId): ?array
    {
        $code = self::normalize($code);
        $result = null;

        Json::update($this->path, function ($data) use ($code, $userId, &$result) {
            if (!isset($data['codes'][$code])) {
                return $data;
            }
            $share = &$data['codes'][$code];
            if ($share['user_id'] !== $userId && !in_array($userId, $share['members'], true)) {
                $share['members'][] = $userId;
            }
            $result = ['code' => $code] + $share;
            return $data;
        }, ['codes' => []]);

        return $result;
    }

    /** @return array[] shares the user joined (not their own) */
    public function joinedBy(string $userId): array
    {
        $result = [];
        foreach (Json::read($this->path, ['codes' => []])['codes'] ?? [] as $code => $share) {
            if (in_array($userId, $share['members'], true)) {
                $result[] = ['code' => (string) $code] + $share;
            }
        }
        return $result;
    }

    public function findJoined(string $userId, string $sessionId): ?array
    {
        foreach ($this->joinedBy($userId) as $share) {
            if ($share['session_id'] === $sessionId) {
                return $share;
            }
        }
        return null;
    }

    public function leave(string $userId, string $sessionId): void
    {
        Json::update($this->path, function ($data) use ($userId, $sessionId) {
            foreach ($data['codes'] ?? [] as $code => $share) {
                if ($share['session_id'] === $sessionId) {
                    $data['codes'][$code]['members'] = array_values(
                        array_filter($share['members'], fn($m) => $m !== $userId)
                    );
                }
            }
            return $data;
        }, ['codes' => []]);
    }

    /** Drops the session's code, e.g. when the chat is deleted. */
    public function revoke(string $userId, string $sessionId): void
    {
        Json::update($this->path, function ($data) use ($userId, $sessionId) {
            $data['codes'] = self::withoutSession($data['codes'] ?? [], $userId, $sessionId);
            return $data;
        }, ['codes' => []]);
    }

    private static function withoutSession(array $codes, string $userId, string $sessionId): array
    {
        return array_filter(
            $codes,
            fn($s) => !($s['user_id'] === $userId && $s['session_id'] === $sessionId)
        );
    }
}
