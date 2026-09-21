<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LavazhoLarjetCmimi extends Model
{
    protected $table = 'lavazho_larjet_cmimi';
    protected $guarded = [];
    protected $casts = [
        'vlera' => 'float',
    ];

    public function sherbimi(): BelongsTo
    {
        return $this->belongsTo(LavazhoLarjetLista::class, 'id_sherbimit');
    }

    public function monedha(): BelongsTo
    {
        return $this->belongsTo(Monedhat::class, 'id_monedhes');
    }
    public function kategoria(): BelongsTo
    {
        return $this->belongsTo(KategoriteEMjeteve::class, 'id_kategoria_mjetit');
    }

}
