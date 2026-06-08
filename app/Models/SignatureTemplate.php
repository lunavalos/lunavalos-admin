<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignatureTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'html_content',
        'css_content',
        'fields',
        'is_active',
        'is_private',
    ];

    protected $casts = [
        'fields'     => 'array',
        'is_active'  => 'boolean',
        'is_private' => 'boolean',
    ];

    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}
