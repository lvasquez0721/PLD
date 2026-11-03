<?php

namespace App\Models\Ebr;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EbrEjercicio extends Model
{
    use HasFactory;

    protected $table = 'ebr_ejercicios';

    protected $fillable = [
        'anio',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'version_metodologia',
        'mitigantes',
        'resultados',
        'totales',
        'comparativo',
        'advertencias',
        'observaciones',
        'importado_por',
        'calculado_por',
        'calculado_at',
        'cerrado_at',
    ];

    protected $casts = [
        'anio' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'mitigantes' => 'array',
        'resultados' => 'array',
        'totales' => 'array',
        'comparativo' => 'array',
        'advertencias' => 'array',
        'calculado_at' => 'datetime',
        'cerrado_at' => 'datetime',
    ];

    public function polizas(): HasMany
    {
        return $this->hasMany(EbrPoliza::class, 'ejercicio_id');
    }

    public function siniestros(): HasMany
    {
        return $this->hasMany(EbrSiniestro::class, 'ejercicio_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(EbrAlerta::class, 'ejercicio_id');
    }

    public function importaciones(): HasMany
    {
        return $this->hasMany(EbrImportacion::class, 'ejercicio_id');
    }

    public function importadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'importado_por');
    }

    public function calculadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculado_por');
    }

    public function scopeCalculados($query)
    {
        return $query->whereIn('estado', ['calculado', 'cerrado']);
    }
}
