<?php

namespace App\Support;

class PublicUrl
{
    public static function to(string $path): string
    {
        $base = (string) config('connectors.public_url');

        return $base === '' ? url($path) : rtrim($base, '/').'/'.ltrim($path, '/');
    }
}
