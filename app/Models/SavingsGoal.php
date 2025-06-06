<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'target_amount',
        'saved_amount',
        'target_date',
        'description',
        'is_achieved',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'saved_amount' => 'decimal:2',
        'target_date' => 'date',
        'is_achieved' => 'boolean',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}