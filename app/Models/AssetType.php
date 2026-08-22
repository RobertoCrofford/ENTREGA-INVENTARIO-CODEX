<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    protected $table = 'tipos_activo';

    public $timestamps = false;

    protected $guarded = [];
}
