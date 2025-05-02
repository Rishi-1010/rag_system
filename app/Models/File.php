<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $fillable = [
        'filename',
        'original_name',
        'path',
        'size',
        'mime_type',
        'embedding_status'
    ];

    protected $casts = [
        'size' => 'integer',
    ];
} 