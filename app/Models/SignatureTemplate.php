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
    ];

    protected $casts = [
        'fields'    => 'array',
        'is_active' => 'boolean',
    ];

    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}
