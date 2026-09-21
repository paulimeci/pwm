<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LavazhoLarjetLista extends Model
{
    protected $table = 'lavazho_larjet_lista';
    protected $guarded = [];

    public function cmimet(): HasMany
    {
        return $this->hasMany(LavazhoLarjetCmimi::class, 'id_sherbimit');
    }



}
