<?php

namespace Philipp\Minichat\Providers;

interface ProviderInterface
{
    /**
     * Streams a chat completion, calling $onDelta(string $text) for every
     * text chunk as it arrives.
     *
     * @param array{
     *   apiKey: string,
     *   model: string,
     *   messages: array<int, array{role: string, text: string}>,
     *   onDelta: callable(string): void
     * } $args
     * @return array{input_tokens: int, output_tokens: int}
     */
    public function stream(array $args): array;
}
