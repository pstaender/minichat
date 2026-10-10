<?php

namespace Philipp\Minichat;

/**
 * Stores each user's reusable "roles" (a name + a rules/system-prompt
 * text) in storage/roles/{user_id}.json. A chat session can be scoped to
 * a role so the role's prompt is applied to every message in that chat —
 * e.g. a Python tutor persona or the rules of a game.
 */
class RoleStore
{
    public function __construct(private string $dir)
    {
    }

    private function path(string $userId): string
    {
        return $this->dir . '/' . Usage::safe($userId) . '.json';
    }

    /** @return array<int, array<string, mixed>> */
    public function list(string $userId): array
    {
        $data = Json::read($this->path($userId), ['roles' => []]);
        $roles = $data['roles'] ?? [];
        usort($roles, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
        return $roles;
    }

    public function get(string $userId, string $roleId): ?array
    {
        foreach ($this->list($userId) as $r) {
            if ($r['id'] === $roleId) {
                return $r;
            }
        }
        return null;
    }

    public function create(string $userId, string $name, string $prompt): array
    {
        $role = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'prompt' => $prompt,
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ];

        Json::update($this->path($userId), function ($data) use ($role) {
            $data['roles'] = $data['roles'] ?? [];
            $data['roles'][] = $role;
            return $data;
        }, ['roles' => []]);

        return $role;
    }

    /** @param callable(array):array $mutator receives and returns the role array */
    public function update(string $userId, string $roleId, callable $mutator): ?array
    {
        $result = null;

        Json::update($this->path($userId), function ($data) use ($roleId, $mutator, &$result) {
            $data['roles'] = $data['roles'] ?? [];
            foreach ($data['roles'] as &$r) {
                if ($r['id'] === $roleId) {
                    $r = $mutator($r);
                    $r['updated_at'] = gmdate('c');
                    $result = $r;
                    break;
                }
            }
            unset($r);
            return $data;
        }, ['roles' => []]);

        return $result;
    }

    public function delete(string $userId, string $roleId): void
    {
        Json::update($this->path($userId), function ($data) use ($roleId) {
            $data['roles'] = array_values(array_filter(
                $data['roles'] ?? [],
                fn($r) => $r['id'] !== $roleId
            ));
            return $data;
        }, ['roles' => []]);
    }
}
