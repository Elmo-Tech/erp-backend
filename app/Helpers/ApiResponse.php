<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Return a success response
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Return an error response
     *
     * @param string $message
     * @param int $statusCode
     * @param array $errors
     * @return JsonResponse
     */
    public static function error(
        string $message = 'Error',
        int $statusCode = 400,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error response
     *
     * @param array $errors
     * @param string $message
     * @return JsonResponse
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return self::error($message, 422, $errors);
    }

    /**
     * Return a not found response
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function notFound(
        string $message = 'Resource not found'
    ): JsonResponse {
        return self::error($message, 404);
    }

    /**
     * Return an unauthorized response
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function unauthorized(
        string $message = 'Unauthorized'
    ): JsonResponse {
        return self::error($message, 401);
    }

    /**
     * Return a forbidden response
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function forbidden(
        string $message = 'Forbidden'
    ): JsonResponse {
        return self::error($message, 403);
    }
}
