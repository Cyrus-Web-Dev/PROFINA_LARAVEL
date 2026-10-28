<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyHoliday extends Model
{
    protected $fillable = ['date', 'name', 'type', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
