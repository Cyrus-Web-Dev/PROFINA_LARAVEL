<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'delivered_at' => 'datetime', 'next_retry_at' => 'datetime'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }
}
