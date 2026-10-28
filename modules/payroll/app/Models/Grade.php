<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $guarded = [];

    protected $casts = ['min_salary' => 'float', 'max_salary' => 'float', 'requires_dual_approval' => 'boolean', 'is_active' => 'boolean'];

    public function inBand(float $salary): bool
    {
        return $salary >= (float) $this->min_salary && ($this->max_salary <= 0 || $salary <= (float) $this->max_salary);
    }
}
