<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

class MCSetupsLicense extends Model
{
    protected $table = 'mcsetups_licenses';

    protected $fillable = [
        'license_key',
        'store_url',
        's3_endpoint',
        's3_access_key',
        's3_secret_key',
        's3_bucket',
        's3_region',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'license_key',
        's3_access_key',
        's3_secret_key',
    ];

    public function hasS3Config(): bool
    {
        return !empty($this->s3_endpoint) && !empty($this->s3_access_key) && !empty($this->s3_secret_key) && !empty($this->s3_bucket);
    }
}