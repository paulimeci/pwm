<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LavazhoKryejOperacionet extends Model
{
    protected $table = 'lavazho_kryej_operacionet';

    protected $guarded = [];

    protected $casts = [
        'vlera' => 'float',
        'nisja' => 'datetime',
        'ikja'  => 'datetime',
    ];

    public function operatori(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_operatori');
    }

    public function sherbimi(): BelongsTo
    {
        return $this->belongsTo(LavazhoLarjetLista::class, 'id_operacionit');
    }

    public function kategoria(): BelongsTo
    {
        return $this->belongsTo(KategoriteEMjeteve::class, 'mjeti_kategoria_id');
    }

    public function monedha(): BelongsTo
    {
        return $this->belongsTo(Monedhat::class, 'id_monedha');
    }
}
