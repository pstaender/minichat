<?php

namespace Philipp\Minichat\Providers;

/**
 * Streams from Anthropic's Messages API (used for the "anthropic" / Claude provider).
 */
class AnthropicProvider implements ProviderInterface
{
    public function stream(array $args): array
    {
        $apiKey = $args['apiKey'];
        $model = $args['model'];
        $onDelta = $args['onDelta'];

        $system = null;
        $payloadMessages = [];

        foreach ($args['messages'] as $m) {
            if ($m['role'] === 'system') {
                $system = trim(($system ?? '') . "\n" . $m['text']);
                continue;
            }

            $payloadMessages[] = ['role' => $m['role'], 'content' => $m['text']];
        }

        $payload = [
            'model' => $model,
            'max_tokens' => 8192,
            'stream' => true,
            'messages' => $payloadMessages,
        ];
        if ($system) {
            $payload['system'] = $system;
        }

        $body = json_encode($payload);
        $usage = ['input_tokens' => 0, 'output_tokens' => 0];
        $buffer = '';
        $rawBody = '';

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$buffer, &$rawBody, $onDelta, &$usage) {
                $rawBody .= $chunk;
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $pos), "\r");
                    $buffer = substr($buffer, $pos + 1);

                    if (!str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $data = trim(substr($line, 5));
                    if ($data === '') {
                        continue;
                    }

                    $json = json_decode($data, true);
                    if (!is_array($json)) {
                        continue;
                    }

                    $type = $json['type'] ?? '';

                    if ($type === 'content_block_delta') {
                        $text = $json['delta']['text'] ?? null;
                        if (is_string($text) && $text !== '') {
                            $onDelta($text);
                        }
                    } elseif ($type === 'message_start') {
                        $usage['input_tokens'] = $json['message']['usage']['input_tokens'] ?? $usage['input_tokens'];
                        $usage['output_tokens'] = $json['message']['usage']['output_tokens'] ?? $usage['output_tokens'];
                    } elseif ($type === 'message_delta') {
                        $usage['output_tokens'] = $json['usage']['output_tokens'] ?? $usage['output_tokens'];
                    }
                }

                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($ok === false || $httpCode >= 400) {
            throw new \RuntimeException(self::extractError($rawBody, $httpCode, $curlError));
        }

        return $usage;
    }

    private static function extractError(string $rawBody, int $httpCode, string $curlError): string
    {
        $json = json_decode($rawBody, true);
        $message = $json['error']['message'] ?? null;
        if ($message) {
            return "Anthropic error (HTTP $httpCode): $message";
        }
        return "Anthropic request failed (HTTP $httpCode)" . ($curlError ? ": $curlError" : '');
    }
}
