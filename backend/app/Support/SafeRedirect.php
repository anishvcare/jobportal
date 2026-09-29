<?php

namespace App\Support;

/**
 * Guards against open redirects: only same-app relative paths are allowed.
 */
final class SafeRedirect
{
    public static function path(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || strlen($value) > 512) {
            return null;
        }

        // Must be a single-slash relative path: rejects "//evil.com", "/\evil.com", "https://..."
        if (! str_starts_with($value, '/') || str_starts_with($value, '//') || str_contains($value, '\\')) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return null;
        }

        return $value;
    }
}
