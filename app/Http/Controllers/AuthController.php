<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\AuthRegisterRequest;
use App\Http\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(AuthLoginRequest $request)
    {
        try {
            return $this->authService->login($request->validated('email'), $request->validated('password'))['response'];
        } catch (\Throwable $th) {
            \Log::error('Login error: ' . $th->getMessage());
            return response()->json([
                'status' => '0',
                'message' => 'Login failed! ' . $th->getMessage()
            ]);
        }
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request);
        return response()->json([
            'status' => 1,
            'message' => 'Logged out successfully!'
        ]);

    }


    public function register(AuthRegisterRequest $request)
    {
        try {
            return $this->authService->register($request->validated());

        } catch (\Throwable $th) {

            \Log::error('Register error: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Registration failed!' .$th->getMessage()
            ], 500);
        }
    }
}
