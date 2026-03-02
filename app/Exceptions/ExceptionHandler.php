<?php

namespace App\Exceptions;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;

class ExceptionHandler
{
    /**
     * Handle API exceptions.
     *
     * @param Throwable $e
     * @return JsonResponse
     */
    public function handleApiException(Throwable $e): JsonResponse
    {
        // Handle Laravel Authentication Exceptions
        if ($e instanceof AuthenticationException || $e->getMessage() == 'Route [login] not defined.')
            return $this->apiResponse($e, 'unauthenticated', 401, 401);


        if ($e instanceof AuthorizationException)
            return $this->apiResponse($e, 'unauthorized', 403, 403);


        // Handle other exceptions
        if ($e instanceof NotFoundHttpException)
            return $this->apiResponse($e, 'not_found', 404, 404);


        if ($e instanceof ValidationException)
            return $this->apiResponse($e, $e->getMessage(), 422, 422);

        // Handle generic exceptions
        return $this->apiResponse($e, $e->getMessage(), 500, 500);
    }

    /**
     * Create a standardized API error response.
     *
     * @param Throwable $e
     * @param string $message
     * @param int $code
     * @param int $codeResponse
     * @return JsonResponse
     */
    private function apiResponse($e, $message, $code, $codeResponse = 200): JsonResponse
    {
        $response = [
            'status' => false,
            'data' => null,
            'message' => $message,
            'error_code' => $code,
        ];

        // Add debug information in development environment
        if (app()->environment('local', 'development')) {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return response()->json($response, $codeResponse);
    }
}
