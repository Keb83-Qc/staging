<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class TeamTitle extends Model
{
    protected $fillable = ['name'];

    protected $casts = [
        'name' => 'array', // On utilise array pour que Filament puisse écrire dans name.fr
    ];
}
