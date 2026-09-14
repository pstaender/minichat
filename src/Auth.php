<?php

namespace Philipp\Minichat;

/**
 * Login state is kept in the native PHP session (cookie-based), not a
 * custom token file. One session = one logged-in user.
 */
class Auth
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
            'httponly' => true,
        ]);

        session_name('minichat_session');
        session_start();
        self::$started = true;
    }

    public static function login(string $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function userId(): ?string
    {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Release the session write-lock so other requests (e.g. the sidebar
     * polling while a stream is in flight) aren't blocked by PHP's default
     * file-based session handler.
     */
    public static function close(): void
    {
        if (self::$started) {
            session_write_close();
        }
    }
}
