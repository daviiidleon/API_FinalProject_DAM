<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; // Asegúrate de que esta línea esté presente
use Illuminate\Support\Facades\URL; 
class UserProfileController extends Controller
{
    /**
     * Obtener la información del usuario autenticado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Cargar la relación defaultAccount para que se incluya en la respuesta JSON
        $user->load('defaultAccount'); // Carga la relación definida en el modelo User

        // NOTA: La lógica para avatarFullUrl ahora se maneja directamente en el Accessor del modelo User
        // $avatarFullUrl = $user->avatar_url; // Ya devuelve la URL completa gracias al Accessor

        return response()->json([
            'user' => $user->makeHidden('email_verified_at') // Oculta 'email_verified_at' si no lo necesitas en el frontend
            // Laravel automáticamente serializará 'defaultAccount' si está cargada
            // y 'avatar_url' ya será la URL completa debido al Accessor.
        ]);
    }

    /**
     * Actualizar la información del usuario autenticado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'currency' => ['required', 'string', 'size:3'],
            'default_account_id' => ['nullable', 'exists:accounts,id,user_id,' . $user->id], // ¡NUEVO: Validar default_account_id!
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            // 'avatar' => ['nullable', 'image', 'max:2048'], // Si el avatar se sube con este mismo endpoint de actualización
        ];

        if ($request->filled('password')) {
            $rules['current_password'] = ['required', 'string'];
        }

        try {
            $validatedData = $request->validate($rules);
        } catch (ValidationException $e) {
            Log::error("Error de validación al actualizar perfil: ", ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        $user->name = $validatedData['name'];
        $user->email = $validatedData['email'];
        $user->currency = $validatedData['currency'];

        // ¡NUEVO: Actualizar default_account_id si está presente en la solicitud!
        // Laravel 10+ hasMethod 'has' or 'filled' better than isset for requests.
        if ($request->has('default_account_id')) { // Usamos has() porque puede ser null intencionalmente
            $user->default_account_id = $validatedData['default_account_id'];
        }


        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['La contraseña actual es incorrecta.'],
                ]);
            }
            $user->password = Hash::make($validatedData['password']);
        }

        // Si el avatar se sube junto con otros campos del perfil en este mismo endpoint,
        // necesitarías la lógica de subida de avatar aquí:
        // if ($request->hasFile('avatar')) {
        //     // Eliminar antiguo avatar si existe y guardar el nuevo
        //     if ($user->avatar_url && !str_contains($user->avatar_url, 'default-avatar.png')) { // Evitar borrar el default
        //         $relativePathForDeletion = str_replace(env('APP_URL') . '/storage/', '', $user->avatar_url);
        //         if (Storage::disk('public')->exists($relativePathForDeletion)) {
        //             Storage::disk('public')->delete($relativePathForDeletion);
        //         }
        //     }
        //     $path = $request->file('avatar')->store('avatars', 'public');
        //     $user->avatar_url = env('APP_URL') . '/storage/' . $path;
        // }


        $user->save();

        // Recargar la relación defaultAccount antes de devolver el usuario en la respuesta
        $user->load('defaultAccount');

        return response()->json([
            'message' => 'Perfil actualizado exitosamente.',
            'user' => $user->makeHidden('email_verified_at') // Devolver el objeto User completo y cargado
        ]);
    }

    /**
     * Almacenar/Actualizar el avatar del usuario autenticado.
     * Este método se mantiene separado para la lógica de solo subir avatar,
     * pero también actualiza otros campos de perfil si se envían (como el frontend de JavaFX).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storeAvatar(Request $request)
    {
        $user = $request->user();

        // Validaciones para el avatar y otros campos de perfil que podrían enviarse junto
        $rules = [
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            // Asegúrate de incluir las reglas de validación para name, email, currency
            // ya que tu cliente JavaFX envía estos campos junto con el avatar.
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'currency' => ['required', 'string', 'size:3'],
            'default_account_id' => ['nullable', 'exists:accounts,id,user_id,' . $user->id], // ¡NUEVO: Validar default_account_id aquí también!
        ];

        try {
            $validatedData = $request->validate($rules);
        } catch (ValidationException $e) {
            Log::error("Error de validación al subir avatar/actualizar perfil: ", ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Error de validación al subir avatar.',
                'errors' => $e->errors()
            ], 422);
        }

        // Actualizar campos de perfil (name, email, currency, default_account_id)
        $user->name = $validatedData['name'];
        $user->email = $validatedData['email'];
        $user->currency = $validatedData['currency'];

        // ¡NUEVO: Actualizar default_account_id si está presente en la solicitud!
        if ($request->has('default_account_id')) {
            $user->default_account_id = $validatedData['default_account_id'];
        }

        // Eliminar el avatar antiguo si existe y no es el por defecto
        if ($user->avatar_url && !str_contains($user->avatar_url, 'default-avatar.png')) {
            // Extraer la ruta relativa del avatar antiguo para el Storage::delete()
            // Asegúrate de que esta lógica coincida con cómo guardas las URLs de avatar
            // (ej. si guardas la URL completa o solo la parte 'avatars/...')
            $currentAvatarPath = str_replace(env('APP_URL') . '/storage/', '', $user->avatar_url);
            $currentAvatarPath = str_replace('/storage/', '', $currentAvatarPath); // Por si se guardó solo /storage/...
            $currentAvatarPath = ltrim($currentAvatarPath, '/'); // Asegurar que no haya doble barra

            if (!empty($currentAvatarPath) && Storage::disk('public')->exists($currentAvatarPath)) {
                Storage::disk('public')->delete($currentAvatarPath);
                Log::info("Avatar antiguo eliminado: " . $currentAvatarPath);
            } else {
                Log::warning("No se encontró el avatar antiguo para eliminar (URL en DB: " . $user->avatar_url . ", Path intentado: " . $currentAvatarPath . ")");
            }
        }

        // Guardar la nueva imagen. $path contendrá la ruta relativa, ej. 'avatars/nombre_unico.jpg'
        $path = $request->file('avatar')->store('avatars', 'public');

        Log::info("DEBUG Laravel: Path guardado en Storage (relativo): " . $path);

        // Construir la URL completa y guardarla en la DB
        $avatarFullUrl = env('APP_URL') . '/storage/' . $path;

        Log::info("DEBUG Laravel: URL completa GENERADA MANUALMENTE: " . $avatarFullUrl);

        // Actualizar la URL del avatar en la base de datos con la URL completa
        $user->avatar_url = $avatarFullUrl;
        $user->save();

        // Recargar la relación defaultAccount antes de devolver el usuario en la respuesta
        $user->load('defaultAccount');

        // Devolver la respuesta con la URL completa y el usuario actualizado
        return response()->json([
            'message' => 'Avatar actualizado exitosamente.',
            'avatar_url' => $avatarFullUrl, // Devolvemos la URL completa (por si acaso el frontend la usa directamente)
            'user' => $user->makeHidden('email_verified_at') // Devolver el objeto User completo y cargado
        ]);
    }
}