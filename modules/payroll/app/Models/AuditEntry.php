<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Baris rantai audit. Tabelnya append-only (trigger database menolak UPDATE/DELETE). */
class AuditEntry extends Model
{
    protected $table = 'audit_ledger';

    public $timestamps = false;

    protected $guarded = [];
}
