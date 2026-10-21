<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmailSignature extends Model
{
    use SoftDeletes;

    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'linkedin', 'twitter'];

    protected $fillable = [
        'client_id',
        'signature_template_id',
        'name',
        'position',
        'email',
        'phone',
        'photo_path',
        'social_links',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'social_links'   => 'array',
        'is_active'      => 'boolean',
        'last_viewed_at' => 'datetime',
    ];

    // El token es la llave del link público y de las URLs de imagen.
    protected static function booted(): void
    {
        static::creating(function (EmailSignature $signature) {
            $signature->token ??= Str::random(40);
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function signatureTemplate()
    {
        return $this->belongsTo(SignatureTemplate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** La plantilla propia, o la asignada al cliente. */
    public function effectiveTemplate(): ?SignatureTemplate
    {
        return $this->signatureTemplate ?? $this->client?->signatureTemplate;
    }

    public function publicUrl(): string
    {
        return route('email-signatures.public', $this->token);
    }
}
