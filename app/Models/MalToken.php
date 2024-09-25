<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MalToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'token',
        'refreshToken',
    ];

    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
