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
        'rfc',
        'tax_regime',
        'cfdi_use',
        'tax_zip_code',
        'fiscal_address',
        'legal_name',
        'applies_iva',
        'iva_rate',
        'applies_isr_retention',
        'isr_retention_rate',
        'applies_iva_retention',
        'iva_retention_rate',
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
        'applies_iva' => 'boolean',
        'iva_rate' => 'decimal:4',
        'applies_isr_retention' => 'boolean',
        'isr_retention_rate' => 'decimal:4',
        'applies_iva_retention' => 'boolean',
        'iva_retention_rate' => 'decimal:4',
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

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function socialPosts()
    {
        return $this->hasMany(SocialPost::class);
    }

    public function assets()
    {
        return $this->hasMany(ClientAsset::class)->latest();
    }
}
