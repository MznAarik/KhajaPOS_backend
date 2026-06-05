<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $statusCode = 200): JsonResponse
    {
        $payload = ['status' => 1, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $statusCode);
    }

    public static function error(string $message = 'Request failed.', int $statusCode = 400, array $extra = []): JsonResponse
    {
        return response()->json([
            'status' => 0,
            'message' => $message,
            ...$extra,
        ], $statusCode);
    }

    public static function exception(\Throwable $exception, string $fallback = 'Request failed.', int $statusCode = 500): JsonResponse
    {
        $debug = (bool) config('app.debug');
        $message = self::exceptionMessage($exception, $fallback);

        return self::error(
            $message ?? ($debug ? $exception->getMessage() : $fallback),
            $statusCode,
            $debug
                ? [
                    'error' => $fallback,
                    'exception' => $exception::class,
                ]
                : []
        );
    }

    private static function exceptionMessage(\Throwable $exception, string $fallback): ?string
    {
        if (!$exception instanceof QueryException) {
            return null;
        }

        $message = $exception->getMessage();

        if (!str_contains($message, 'SQLSTATE[23505]')) {
            return $fallback;
        }

        if (preg_match('/Key \(([^)]+)\)=\(([^)]+)\) already exists\./', $message, $matches)) {
            $column = $matches[1];
            $value = $matches[2];

            return match ($column) {
                'table_no' => "Table number {$value} already exists. Please use a different table number.",
                'qr_code' => 'This table QR code already exists. Please generate a new QR code.',
                'email' => "Email {$value} is already registered.",
                'name' => "{$value} already exists. Please use a different name.",
                default => 'A record with the same value already exists. Please use a different value.',
            };
        }

        return 'A record with the same value already exists. Please use a different value.';
    }
}
