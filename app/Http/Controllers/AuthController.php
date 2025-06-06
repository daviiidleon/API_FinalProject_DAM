<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; // Necesitas esto para Storage::url()
use Illuminate\Support\Facades\URL; // Para asset()

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'currency' => ['nullable', 'string', 'size:3'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al registrar usuario: ", ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'currency' => $validatedData['currency'] ?? 'EUR',
            'avatar_url' => null, // Por defecto, sin avatar al registrarse. El accessor lo convertirá a la URL por defecto.
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $defaultCategories = [
            ['name' => 'Salario', 'type' => 'income', 'description' => 'Ingresos regulares'],
            ['name' => 'Comida', 'type' => 'expense', 'description' => 'Gastos de alimentos y restaurantes'],
            ['name' => 'Transporte', 'type' => 'expense', 'description' => 'Gastos de movilidad'],
            ['name' => 'Vivienda', 'type' => 'expense', 'description' => 'Alquiler/hipoteca, servicios'],
            ['name' => 'Entretenimiento', 'type' => 'expense', 'description' => 'Ocio, hobbies, cultura'],
            ['name' => 'Salud', 'type' => 'expense', 'description' => 'Consultas médicas, medicinas'],
            ['name' => 'Ahorros', 'type' => 'expense', 'description' => 'Dinero para metas de ahorro'],
            ['name' => 'Otros', 'type' => 'expense', 'description' => 'Gastos no categorizados'],
        ];

        $user->categories()->createMany($defaultCategories);

        // --- CAMBIO CLAVE AQUÍ PARA REGISTER ---
        // Asegúrate de que avatar_url en la respuesta sea una URL absoluta
        $avatarFullUrl = $user->avatar_url 
                         ? (filter_var($user->avatar_url, FILTER_VALIDATE_URL) ? $user->avatar_url : Storage::url($user->avatar_url))
                         : asset('images/default-avatar.png'); // Si es null en DB, usa tu default asset

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'currency' => $user->currency,
                'avatar_url' => $avatarFullUrl, // Devolver la URL completa
            ],
            'token' => $token,
            'message' => 'Registration successful'
        ], 201);
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al iniciar sesión: ", ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no coinciden con nuestros registros.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->last_login = now();
        $user->save();

        // --- CAMBIO CLAVE AQUÍ PARA LOGIN ---
        // Asegúrate de que avatar_url en la respuesta sea una URL absoluta
        $avatarFullUrl = $user->avatar_url 
                         ? (filter_var($user->avatar_url, FILTER_VALIDATE_URL) ? $user->avatar_url : Storage::url($user->avatar_url))
                         : asset('images/default-avatar.png'); // Si es null en DB, usa tu default asset

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'currency' => $user->currency,
                'avatar_url' => $avatarFullUrl, // Devolver la URL completa
            ],
            'token' => $token,
            'message' => 'Login successful'
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['message' => 'Logout successful']);
        } else {
            return response()->json(['message' => 'No authenticated user to log out.'], 401);
        }
    }

    public function user(Request $request)
    {
        $user = $request->user();
        
        // --- CAMBIO CLAVE AQUÍ PARA user() ---
        // Asegúrate de que avatar_url en la respuesta sea una URL absoluta
        $avatarFullUrl = $user->avatar_url 
                         ? (filter_var($user->avatar_url, FILTER_VALIDATE_URL) ? $user->avatar_url : Storage::url($user->avatar_url))
                         : asset('images/default-avatar.png'); // Si es null en DB, usa tu default asset

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'currency' => $user->currency,
            'avatar_url' => $avatarFullUrl, // Devolver la URL completa
        ]);
    }
}