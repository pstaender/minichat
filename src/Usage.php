<?php

namespace Philipp\Minichat;

/**
 * Tracks per-user, per-day token usage in storage/usage/{user_id}.json
 * and enforces the daily_token_limit / daily_token_limit_output from
 * chat_config.json.
 */
class Usage
{
    public function __construct(private string $dir)
    {
    }

    private function path(string $userId): string
    {
        return $this->dir . '/' . self::safe($userId) . '.json';
    }

    public static function safe(string $id): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '_', $id);
    }

    private function today(): string
    {
        return gmdate('Y-m-d');
    }

    private function blank(): array
    {
        return ['date' => $this->today(), 'input_tokens' => 0, 'output_tokens' => 0];
    }

    public function get(string $userId): array
    {
        $data = Json::read($this->path($userId), null);
        if (!is_array($data) || ($data['date'] ?? null) !== $this->today()) {
            return $this->blank();
        }
        return $data;
    }

    /**
     * Returns null when the user is within their limits, or
     * ['code' => ..., 'message' => ...] when a daily limit has been hit.
     */
    public function checkLimit(array $user, string $userId): ?array
    {
        $usage = $this->get($userId);
        $total = $usage['input_tokens'] + $usage['output_tokens'];

        $limit = $user['daily_token_limit'] ?? null;
        $outputLimit = $user['daily_token_limit_output'] ?? null;

        if ($limit !== null && $total >= $limit) {
            return ['code' => 'daily_limit_reached', 'message' => 'Daily token limit reached. Try again tomorrow.'];
        }

        if ($outputLimit !== null && $usage['output_tokens'] >= $outputLimit) {
            return ['code' => 'daily_output_limit_reached', 'message' => 'Daily output token limit reached. Try again tomorrow.'];
        }

        return null;
    }

    public function add(string $userId, int $inputTokens, int $outputTokens): array
    {
        return Json::update($this->path($userId), function ($data) use ($inputTokens, $outputTokens) {
            if (!is_array($data) || ($data['date'] ?? null) !== $this->today()) {
                $data = $this->blank();
            }
            $data['input_tokens'] += max(0, $inputTokens);
            $data['output_tokens'] += max(0, $outputTokens);
            return $data;
        }, $this->blank());
    }
}
