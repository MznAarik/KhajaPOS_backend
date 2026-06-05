<?php

namespace App\Http\Services;

use App\Models\Business;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class AuthService
{
    public function login(string $email, string $password): JsonResponse
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($user->email_verified_at === null) {
            return response()->json([
                'status' => 0,
                'message' => 'Account is not verified.',
            ], 403);
        }

        $authUser = Auth::user();
        $accessToken = $authUser->createToken('authToken')->accessToken;

        return response()->json([
            'status' => 1,
            'message' => 'Login successful.',
            'data' => [
                'user' => $authUser,
                'token_type' => 'Bearer',
                'access_token' => $accessToken,
            ],
        ]);
    }

    public function logout(Request $request): void
    {
        $token = $request->user()?->token();
        if ($token) {
            $token->revoke();
        }
    }

    public function register(array $data): JsonResponse
    {
        return DB::transaction(function () use ($data) {
            $requestedRole = $data['role'] ?? 'user';
            // if ($requestedRole === 'admin') {
            //     $authUser = Auth::user();
            //     if (!$authUser || $authUser->role !== 'super_admin') {
            //         return response()->json(['status' => 0, 'message' => 'Unauthorized to create admin account!'], 403);
            //     }
            // }

            $user = User::create([
                'name' => $data['name'] ?? 'N/A',
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $requestedRole,
            ]);

            $business = $data['business'] ?? [];
            Business::create([
                'user_id' => $user->id,
                'name' => $business['name'] ?? 'N/A',
                'business_type' => $business['business_type'] ?? 'N/A',
                'phone' => $business['phone'] ?? 'N/A',
                'email' => $business['email'] ?? 'N/A',
                'address' => $business['address'] ?? 'N/A',
                'created_by' => $user->id,
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Account created successfully.',
            ], 201);
        });
    }
}
