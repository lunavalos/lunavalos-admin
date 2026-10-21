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

    // Cambia lo que muestran las firmas: invalida las imágenes ya resueltas.
    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\SignatureUrls::flushCache());
        static::deleted(fn () => \App\Support\SignatureUrls::flushCache());
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function media()
    {
        return $this->hasMany(SignatureMedia::class);
    }
}
