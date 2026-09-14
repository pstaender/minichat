<?php

namespace Philipp\Minichat\Providers;

/**
 * Streams from OpenAI's Chat Completions API (used for the "openai" / ChatGPT provider).
 */
class OpenAIProvider implements ProviderInterface
{
    public function stream(array $args): array
    {
        $apiKey = $args['apiKey'];
        $model = $args['model'];
        $onDelta = $args['onDelta'];

        $payloadMessages = array_map(
            fn(array $m) => ['role' => $m['role'], 'content' => $m['text']],
            $args['messages']
        );

        $body = json_encode([
            'model' => $model,
            'messages' => $payloadMessages,
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ]);

        $usage = ['input_tokens' => 0, 'output_tokens' => 0];
        $buffer = '';
        $rawBody = '';

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
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
                    if ($data === '' || $data === '[DONE]') {
                        continue;
                    }

                    $json = json_decode($data, true);
                    if (!is_array($json)) {
                        continue;
                    }

                    $delta = $json['choices'][0]['delta']['content'] ?? null;
                    if (is_string($delta) && $delta !== '') {
                        $onDelta($delta);
                    }

                    if (isset($json['usage'])) {
                        $usage['input_tokens'] = $json['usage']['prompt_tokens'] ?? $usage['input_tokens'];
                        $usage['output_tokens'] = $json['usage']['completion_tokens'] ?? $usage['output_tokens'];
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
            return "OpenAI error (HTTP $httpCode): $message";
        }
        return "OpenAI request failed (HTTP $httpCode)" . ($curlError ? ": $curlError" : '');
    }
}
