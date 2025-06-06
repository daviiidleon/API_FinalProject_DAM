<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    // Si tu PK no es 'id', especifícala
    // protected $primaryKey = 'account_id'; // Descomenta si usas 'account_id' en lugar de 'id'

    protected $fillable = [
        'user_id',
        'name',
        'current_balance', // Actualizado
        'currency',        // Agregado
        'type',            // Actualizado
        'is_active',       // Agregado
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        // Si tu PK de Account es 'account_id', especifícala aquí también:
        // return $this->hasMany(Transaction::class, 'account_id', 'account_id');
        return $this->hasMany(Transaction::class); // Si la PK de Account es 'id'
    }
}