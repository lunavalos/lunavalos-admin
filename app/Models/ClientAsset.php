<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAsset extends Model
{
    protected $fillable = [
        'client_id',
        'kind',
        'label',
        'file_path',
        'file_name',
        'mime',
        'url',
        'value',
        'created_by',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public const KINDS = [
        'document',
        'logo',
        'branding',
        'typography',
        'color_palette',
        'url',
        'note',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
