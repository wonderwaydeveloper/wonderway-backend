<?php

namespace App\Exceptions;

use Exception;

class UserNotFoundException extends Exception
{
    public function __construct(string $message = 'کاربر یافت نشد', int $code = 404)
    {
        parent::__construct($message, $code);
    }

    public function render()
    {
        return response()->json([
            'error' => 'User not found',
            'message' => $this->getMessage(),
        ], $this->getCode());
    }
}
