<?php

namespace BYanelli\Roma\Request\ContextualBinding;

use Exception;
use Throwable;

class ContextualBindingException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $prefix = 'Error binding the request using the #[Request] attribute: ';

        parent::__construct($prefix.$message, $code, $previous);
    }
}
