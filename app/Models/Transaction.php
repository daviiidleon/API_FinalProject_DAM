<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'type',
        'amount',
        'description',
        'transaction_date',
        'payee',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2', // Castear a decimal con 2 decimales
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        // Si la PK de Account es 'account_id', especifícala aquí:
        // return $this->belongsTo(Account::class, 'account_id', 'account_id');
        return $this->belongsTo(Account::class); // Si la PK de Account es 'id'
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}