<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'user_id',
        'business_name',
        'contact_name',
        'email',
        'phone',
        'city',
        'agency_source',
        'contract_date',
        'initial_price',
        'is_historical',
        'domain_names',
        'domain_provider',
        'hosting_provider',
        'email_provider',
        'login_credentials',
        'notes',
        'quote_id',
        'quote_file_path',
        'contract_file_path',
        'branding_file_path',
        'receipt_file_path',
        'imap_host',
        'imap_port',
        'imap_tls',
        'pop_host',
        'pop_port',
        'pop_tls',
        'smtp_host',
        'smtp_port',
        'smtp_tls',
        'has_custom_email_config',
        'email_accounts',
        'briefing_context',
        'briefing_target_audience',
        'briefing_competitors',
        'briefing_references',
        'briefing_contact_methods',
        'briefing_current_emails',
        'vault_credentials',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'is_historical' => 'boolean',
        'initial_price' => 'decimal:2',
        'renewal_amount' => 'decimal:2',
        'imap_tls' => 'boolean',
        'pop_tls' => 'boolean',
        'smtp_tls' => 'boolean',
        'has_custom_email_config' => 'boolean',
        'email_accounts' => 'array',
        'vault_credentials' => 'array',
    ];

    /** The primary (legacy) single user linked to this client via clients.user_id */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** All platform users linked to this client via users.client_id */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function costs()
    {
        return $this->hasMany(ClientCost::class);
    }

    public function services()
    {
        return $this->hasMany(ClientService::class);
    }
}
