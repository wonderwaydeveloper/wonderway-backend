<?php

namespace App\Exceptions;

use Exception;

class UnauthorizedActionException extends Exception
{
    public function __construct(string $message = 'شما مجاز به انجام این عمل نیستید', int $code = 403)
    {
        parent::__construct($message, $code);
    }

    public function render()
    {
        return response()->json([
            'error' => 'Unauthorized action',
            'message' => $this->getMessage(),
        ], $this->getCode());
    }
}
