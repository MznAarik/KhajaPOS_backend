<?php

namespace App\Http\Services;

use App\Models\Business;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return ['response' => response()->json(['status' => 0, 'message' => "No user found for '$email'. Please register first!"], 404)];
        }

        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            return ['response' => response()->json(['status' => 0, 'message' => 'Invalid credentials!'], 401)];
        }

        if ($user->email_verified_at === null) {
            return ['response' => response()->json(['status' => 0, 'message' => 'Email not verified! Please verify your email before logging in.'], 403)];
        }

        $authUser = Auth::user();
        $accessToken = $authUser->createToken('authToken')->accessToken;

        return ['response' => response()->json([
            'message' => 'Login Successful!',
            'data' => [
                'status' => 1,
                'user' => $authUser,
                'token_type' => 'Bearer',
                'access_token' => $accessToken,
            ],
        ])];
    }

    public function logout(Request $request): void
    {
        $request->user()->tokens()->update(['revoked' => true]);
    }

    public function register(array $data)
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

            return response()->json(['status' => 1, 'message' => 'Account created successfully'], 200);
        });
    }
}
