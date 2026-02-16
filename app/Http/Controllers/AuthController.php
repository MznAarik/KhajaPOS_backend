<?php

namespace App\Http\Controllers;

use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8'
        ]);
        try {
            $credentials = $request->only('email', 'password');
            $user = User::where('email', $credentials['email'])->first();

            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => "No user found for '$credentials[email]'. Please register first!",
                ], 404);
            }

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Invalid credentials!'
                ], 401);
            }

            $authUser = Auth::user();

            $authUser->tokens()->update(['revoked' => true]);

            $accessToken = $authUser->createToken('authToken')->accessToken;

            return response()->json([
                'message' => 'Login Successful!',
                'data' => [
                    'status' => 1,
                    'user' => Auth::user(),
                    'token_type' => 'Bearer',
                    'access_token' => $accessToken,
                ]
            ]);

        } catch (\Throwable $e) {
            \Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'status' => '0',
                'message' => 'Login failed!'
            ]);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->update(['revoked' => true]);
        return response()->json([
            'status' => 1,
            'message' => 'Logged out successfully!'
        ]);

    }


    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|min:5',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'in:admin,cashier',
        ]);
        try {
            $response = DB::transaction(function () use ($request) {
                $requestedRole = $request->input('role', 'cashier');

                if ($requestedRole === 'admin') {
                    $authUser = Auth::user();

                    if (!$authUser || $authUser->role !== 'admin') {
                        return response()->json([
                            'status' => 0,
                            'message' => 'Unauthorized to create admin account!',
                        ], 403);
                    }
                }

                User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => $request->password,
                    'role' => $requestedRole,
                    'created_at' => now()
                ]);

                return response()->json([
                    'status' => 1,
                    'message' => 'Account created successfully'
                ], 200);
            });

            return $response;

        } catch (\Throwable $e) {
            \Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'status' => '0',
                'message' => 'Login failed!'
            ]);
        }
    }
}
