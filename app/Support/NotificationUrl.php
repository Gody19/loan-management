<?php

namespace App\Support;

class NotificationUrl
{
    public static function relative(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#')) {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }

        $relative = $parts['path'] ?? '/';

        if ($relative === '') {
            $relative = '/';
        }

        if (isset($parts['query'])) {
            $relative .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $relative .= '#'.$parts['fragment'];
        }

        return $relative;
    }
}
