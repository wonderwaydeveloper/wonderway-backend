<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class UnauthorizedException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->message ?: 'شما مجاز به انجام این عملیات نیستید',
            'error' => 'UNAUTHORIZED',
        ], 403);
    }
}
