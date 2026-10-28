<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationInbox extends Model
{
    protected $table = 'integration_inbox';

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'processed_at' => 'datetime'];
}
