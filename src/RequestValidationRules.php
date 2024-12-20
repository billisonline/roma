<?php

namespace BYanelli\Roma;

use BYanelli\Roma\Properties\Property;
use BYanelli\Roma\Properties\Source;
use BYanelli\Roma\Properties\Type;
use Illuminate\Support\Str;

readonly class RequestValidationRules
{
    private array $rules;

    /**
     * @param Property[] $properties
     */
    public function __construct(array $properties)
    {
        $this->rules = $this->getValidationRulesFromProperties($properties);
    }

    /**
     * @param Property[] $properties
     * @return array
     */
    private function getValidationRulesFromProperties(array $properties): array
    {
        $rules = [];

        foreach ($properties as $property) {
            $propertyRules = $property->rules;

            if ($property->type != Type::Mixed) {
                $propertyRules[] = match ($property->type) {
                    Type::Bool => 'boolean',
                    Type::Int => 'integer',
                    Type::Float => 'numeric',
                    Type::Date => 'date',
                    Type::String => 'string',
                    default => throw new RuntimeException("Unsupported type: {$property->type->name}"),
                };
            }

            if ($property->isRequired) {
                $propertyRules[] = 'required';
            }

            $rules[$this->getAccessKey($property)] = $propertyRules;
        }

        return $rules;
    }


    private function getAccessKey(Property $property): string
    {
        // todo DRY
        $source = match ($property->source) {
            Source::Input => 'input',
            Source::Query => 'query',
            Source::Body => 'body',
            Source::Header => 'header',
            Source::File => 'file',
            Source::Object => 'request',
        };

        $key = ($property->source == Source::Header)
            ? Str::of($property->key)->upper()->replace('-', '_')->toString()
            : $property->key;

        return $source.'.'.$key;
    }

    public function toArray(): array
    {
        return $this->rules;
    }
}
