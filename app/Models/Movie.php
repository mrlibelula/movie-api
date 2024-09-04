<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'release_date', 'genre'];

    protected $hidden = ['pivot'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'watch_later');
    }

    // If you have a separate Genre model and table
    public function genre()
    {
        return $this->belongsTo(Genre::class);
    }
}
