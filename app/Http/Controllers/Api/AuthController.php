<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * A precomputed bcrypt hash (cost matches the app's Hash::make output) with no
     * corresponding plaintext. Used to keep login response time constant whether or
     * not the email exists, so timing can't be used to enumerate registered accounts.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$UcNiTHzClp.CTq//UX9ayeoRhYCIVg0vL9dYzcjZD0tDswJ/GZft2';

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'Registered successfully.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Always run Hash::check, even for an unknown email, against a hash of equal
        // cost so a nonexistent account doesn't resolve noticeably faster than a
        // wrong password on a real one.
        $hashToCheck = $user->password ?? self::DUMMY_PASSWORD_HASH;
        $passwordMatches = Hash::check($request->validated('password'), $hashToCheck);

        if (! $user || ! $passwordMatches) {
            return $this->error('Invalid credentials.', 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'Logged in successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }
}
