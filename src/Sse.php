<?php

namespace Philipp\Minichat;

/**
 * Minimal Server-Sent-Events emitter. Dumbo itself buffers the whole
 * response body in memory (see Dumbo::send()), so streaming routes write
 * directly to the PHP output buffer and bypass Dumbo's response handling.
 */
class Sse
{
    public static function start(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        ob_implicit_flush(true);
        @set_time_limit(0);
        ignore_user_abort(true);
    }

    public static function emit(string $type, array $payload = []): void
    {
        $payload['type'] = $type;
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
}
