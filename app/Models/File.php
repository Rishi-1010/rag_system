<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'mime_type',
        'embedding_status',
        'original_name',
        'size',
        'path',
        'project_id'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}