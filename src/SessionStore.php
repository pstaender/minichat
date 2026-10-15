<?php

namespace Philipp\Minichat;

/**
 * Stores every user's chat sessions (multiple conversations) in
 * storage/sessions/{user_id}.json.
 */
class SessionStore
{
    public function __construct(private string $dir)
    {
    }

    private function path(string $userId): string
    {
        return $this->dir . '/' . Usage::safe($userId) . '.json';
    }

    /** Summaries for the sidebar, newest first. */
    public function list(string $userId): array
    {
        $data = Json::read($this->path($userId), ['sessions' => []]);
        $sessions = $data['sessions'] ?? [];

        usort($sessions, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

        return array_map(self::summary(...), $sessions);
    }

    /** Sidebar entry for a session, without its messages. */
    public static function summary(array $s): array
    {
        return [
            'id' => $s['id'],
            'title' => $s['title'],
            'provider' => $s['provider'],
            'model' => $s['model'],
            'role_id' => $s['role_id'] ?? null,
            'created_at' => $s['created_at'],
            'updated_at' => $s['updated_at'],
            'message_count' => count($s['messages'] ?? []),
        ];
    }

    public function get(string $userId, string $sessionId): ?array
    {
        $data = Json::read($this->path($userId), ['sessions' => []]);
        foreach ($data['sessions'] ?? [] as $s) {
            if ($s['id'] === $sessionId) {
                return $s;
            }
        }
        return null;
    }

    public function create(
        string $userId,
        string $provider,
        string $model,
        string $title = 'New chat',
        ?string $roleId = null
    ): array {
        $session = [
            'id' => bin2hex(random_bytes(8)),
            'title' => $title,
            'provider' => $provider,
            'model' => $model,
            'role_id' => $roleId,
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'messages' => [],
        ];

        Json::update($this->path($userId), function ($data) use ($session) {
            $data['sessions'] = $data['sessions'] ?? [];
            $data['sessions'][] = $session;
            return $data;
        }, ['sessions' => []]);

        return $session;
    }

    /**
     * @param callable(array):array $mutator receives and returns the session array
     * @param bool $touch bump updated_at (moves the chat to the top of the sidebar)
     */
    public function update(string $userId, string $sessionId, callable $mutator, bool $touch = true): ?array
    {
        $result = null;

        Json::update($this->path($userId), function ($data) use ($sessionId, $mutator, $touch, &$result) {
            $data['sessions'] = $data['sessions'] ?? [];
            foreach ($data['sessions'] as &$s) {
                if ($s['id'] === $sessionId) {
                    $s = $mutator($s);
                    if ($touch) {
                        $s['updated_at'] = gmdate('c');
                    }
                    $result = $s;
                    break;
                }
            }
            unset($s);
            return $data;
        }, ['sessions' => []]);

        return $result;
    }

    public function delete(string $userId, string $sessionId): void
    {
        Json::update($this->path($userId), function ($data) use ($sessionId) {
            $data['sessions'] = array_values(array_filter(
                $data['sessions'] ?? [],
                fn($s) => $s['id'] !== $sessionId
            ));
            return $data;
        }, ['sessions' => []]);
    }
}
