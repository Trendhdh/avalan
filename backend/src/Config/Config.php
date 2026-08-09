<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Config;

/**
 * Config
 *
 * Tiny .env reader. Production's Config/Database.php also builds a
 * MySQL PDO connection from these values and requires several real
 * secrets (JWT signing key, encryption key, Paylov API key, webhook
 * secret — see backend .env.example). The demo needs none of that: it
 * has no database and no external API calls, so this class only reads
 * the two values the demo actually uses (a placeholder "app key" header
 * check and the demo bearer token), both non-secret and safe to ship
 * with a public repository.
 */
final class Config
{
    /** @var array<string,string> */
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $path = __DIR__ . '/../../.env';
        if (!is_file($path)) {
            $path = __DIR__ . '/../../.env.example';
        }
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            self::$values[trim($key)] = trim($value);
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        $value = self::$values[$key] ?? $default;
        return $value === '' ? $default : $value;
    }
}
