<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Importar BelongsTo
use Illuminate\Support\Facades\Storage; // Necesario para Storage::url() si se usara, o para otras lógicas
use Illuminate\Support\Facades\URL;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'currency',
        'default_account_id', // ¡Añadir esta línea!
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login' => 'datetime',
    ];

    // Relaciones existentes
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class, 'user_id');
    }

    /**
     * Get the default account for the user.
     * Añadir esta nueva relación
     */
    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    /**
     * Accessor para el atributo avatar_url.
     * Siempre devuelve una URL absoluta o la URL del avatar por defecto.
     *
     * @param  string|null  $value El valor crudo de avatar_url desde la base de datos.
     * @return string La URL absoluta del avatar.
     */
    public function getAvatarUrlAttribute($value): string
    {
        // Si no hay valor en la base de datos, devuelve la URL de la imagen por defecto.
        if (empty($value)) {
            // Asegúrate de que tu default-avatar.png esté en public/images/
            return asset('images/default-avatar.png');
        }

        // Si el valor ya es una URL absoluta (empieza con http:// o https://), lo devolvemos tal cual.
        // Esto es para compatibilidad con avatares ya guardados como URLs absolutas o externas.
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // Si el valor es una ruta relativa (ej. 'avatars/nombre.jpg' o '/storage/avatars/nombre.jpg'),
        // construimos la URL absoluta usando APP_URL.
        // ltrim($value, '/') es para quitar una posible barra inicial si el valor ya empieza por /storage/
        // Asegúrate de que APP_URL en tu .env esté bien configurado (ej. http://localhost:9007)
        return env('APP_URL') . '/storage/' . ltrim($value, '/');
    }
}