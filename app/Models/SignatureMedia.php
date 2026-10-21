<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignatureMedia extends Model
{
    protected $table = 'signature_media';

    protected $fillable = [
        'signature_template_id',
        'draft_key',
        'name',
        'file_path',
        'mime',
        'width',
        'height',
        'size',
        'created_by',
    ];

    public function signatureTemplate()
    {
        return $this->belongsTo(SignatureTemplate::class);
    }

    /**
     * URL absoluta que se pega en el HTML y viaja dentro de los correos: con
     * el subdominio de firmas si está configurado.
     */
    public function url(): string
    {
        return \App\Support\SignatureUrls::storage($this->file_path);
    }

    public function toLibraryArray(): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'url'    => $this->url(),
            'mime'   => $this->mime,
            'width'  => $this->width,
            'height' => $this->height,
            'size'   => $this->size,
        ];
    }
}
