<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\AuthRegisterRequest;
use App\Http\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(AuthLoginRequest $request)
    {
        try {
            return $this->authService->login($request->validated('email'), $request->validated('password'));
        } catch (\Throwable $th) {
            \Log::error('Login error: ' . $th->getMessage());
            return ApiResponse::exception($th, 'Login failed. Please try again.', 500);
        }
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request);
        return ApiResponse::success(null, 'Logged out successfully!');

    }


    public function register(AuthRegisterRequest $request)
    {
        try {
            return $this->authService->register($request->validated());

        } catch (\Throwable $th) {

            \Log::error('Register error: ' . $th->getMessage());
            return ApiResponse::exception($th, 'Registration failed. Please try again.', 500);
        }
    }
}
