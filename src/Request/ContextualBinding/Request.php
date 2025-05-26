<?php

namespace BYanelli\Roma\Request\ContextualBinding;

use Attribute;
use BYanelli\Roma\Request\RequestMapper;
use Illuminate\Container\BoundMethod;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Validation\ValidationException;
use ReflectionNamedType;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Request implements ContextualAttribute
{
    /**
     * @throws ContextualBindingException
     * @throws \ReflectionException
     * @throws BindingResolutionException
     * @throws ValidationException
     */
    public static function resolve(self $attribute, Container $container)
    {
        foreach (debug_backtrace() as $frame) {
            /** @see BoundMethod::addDependencyForCallParameter() */
            if (!(
                ($frame['class'] == BoundMethod::class)
                && ($frame['function'] == 'addDependencyForCallParameter')
            )) { continue; }

            (count($frame['args']) >= 2) ||
                throw new ContextualBindingException('could not introspect container call stack');

            /** @var ReflectionParameter $parameter */
            (get_class($parameter = $frame['args'][1]) == ReflectionParameter::class) ||
                throw new ContextualBindingException('could not introspect container call stack');

            /** @var ReflectionNamedType $type */
            (get_class($type = $parameter->getType()) == ReflectionNamedType::class) ||
                throw new ContextualBindingException("the parameter $parameter->name must be type-hinted with a class");

            class_exists($className = $type->getName()) ||
                throw new ContextualBindingException("$className does not exist");

            /** @var RequestMapper $mapper */
            $mapper = $container->make(RequestMapper::class);

            return $mapper->mapRequest($className);
        }

        throw new ContextualBindingException('could not find request parameter');
    }
}
