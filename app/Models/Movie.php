<?php

namespace App\Models;

use App\Support\MovieCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'release_date', 'genre_id'];

    protected $hidden = ['pivot'];

    protected static function booted(): void
    {
        static::saved(fn (Movie $movie) => MovieCache::flush($movie->getKey()));
        static::deleted(fn (Movie $movie) => MovieCache::flush($movie->getKey()));
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'movie_user')->withTimestamps();
    }

    public function genre()
    {
        return $this->belongsTo(Genre::class);
    }
}
