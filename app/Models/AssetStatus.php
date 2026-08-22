<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetStatus extends Model
{
    protected $table = 'estados_activo';

    public $timestamps = false;

    protected $guarded = [];
}
