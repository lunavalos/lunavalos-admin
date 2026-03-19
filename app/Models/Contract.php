<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'quote_id',
        'token',
        'status',
        'legal_name',
        'tax_id',
        'fiscal_address',
        'postal_code',
        'legal_representative',
        'csf_file_path',
        'signed_at',
        'signature_ip',
        'pdf_file_path'
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }
}
