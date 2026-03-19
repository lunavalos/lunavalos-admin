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
        'is_active',
    ];
}
