<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CurrentPatient extends Model
{
    public $table = 'current_patients';

    public $timestamps = false;

    public function scopeOwnPatient($query)
    {
        $query->where('department_id', '=', Auth::user()->department_id);
    }
}
