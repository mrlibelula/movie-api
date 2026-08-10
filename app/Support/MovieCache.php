<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class MovieCache
{
    public const TTL = 3600;

    public static function ttl(): int
    {
        return self::TTL;
    }

    public static function allKey(): string
    {
        return 'movies.all';
    }

    public static function key(int $id): string
    {
        return "movie.{$id}";
    }

    public static function flush(?int $id = null): void
    {
        Cache::forget(self::allKey());

        if ($id !== null) {
            Cache::forget(self::key($id));
        }
    }
}
