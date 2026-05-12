<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        } catch (\Throwable $th) {
            \Log::error('Login error: ' . $th->getMessage());
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
        ]);

        try {

            $response = DB::transaction(function () use ($request) {

                $requestedRole = $request->input('role', 'user');

                if ($requestedRole === 'admin') {

                    $authUser = Auth::user();

                    if (!$authUser || $authUser->role !== 'super_admin') {

                        return response()->json([
                            'status' => 0,
                            'message' => 'Unauthorized to create admin account!',
                        ], 403);
                    }
                }

                $user = User::create([
                    'name' => $request->name ?? 'N/A',
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => $requestedRole,
                    'created_at' => now()
                ]);

                $business = Business::create([
                    'user_id' => $user->id,
                    'name' => $request->business['name'] ?? 'N/A',
                    'business_type' => $request->business['business_type'] ?? 'N/A',
                    'phone' => $request->business['phone'] ?? 'N/A',
                    'email' => $request->business['email'] ?? 'N/A',
                    'address' => $request->business['address'] ?? 'N/A',
                    'created_by' => $user->id,
                    'created_at' => now()
                ]);

                return response()->json([
                    'status' => 1,
                    'message' => 'Account created successfully'
                ], 200);
            });

            return $response;

        } catch (\Throwable $th) {

            \Log::error('Register error: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Registration failed!'
            ], 500);
        }
    }
}