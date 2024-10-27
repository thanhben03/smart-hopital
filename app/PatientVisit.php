<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PatientVisit extends Model
{
    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo('App\Patient');
    }

}
