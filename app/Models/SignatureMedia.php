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
     * URL absoluta con el dominio de la petición: es la que se pega en el HTML
     * y viaja dentro de los correos.
     */
    public function url(): string
    {
        return asset('storage/' . $this->file_path);
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
