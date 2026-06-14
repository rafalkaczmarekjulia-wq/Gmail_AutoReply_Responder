<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'reply_prompt')) {
            $userData['reply_prompt'] = User::defaultReplyPrompt();
        }

        $user = User::create($userData);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'reply_prompt' => $user->reply_prompt ?? User::defaultReplyPrompt(),
            'llm_driver' => config('services.llm.driver'),
            'llm_model' => config('services.llm.openai_model'),
        ]);
    }

    public function updateReplyPrompt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reply_prompt' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

        $request->user()->update([
            'reply_prompt' => $data['reply_prompt'],
        ]);

        return response()->json([
            'message' => 'Reply prompt saved',
            'reply_prompt' => $request->user()->reply_prompt,
        ]);
    }
}
