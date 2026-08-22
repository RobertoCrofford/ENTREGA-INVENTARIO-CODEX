<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $table = 'existencias';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
