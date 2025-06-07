<?php

namespace BYanelli\Roma\Request\Contracts;

use Illuminate\Validation\ValidationException;

interface RequestMapper
{
    /**
     * @template T
     *
     * @param  class-string<T>  $className
     * @return T
     *
     * @throws \ReflectionException|ValidationException
     */
    public function mapRequest(string $className);
}
