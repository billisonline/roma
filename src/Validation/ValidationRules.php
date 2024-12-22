<?php

namespace BYanelli\Roma\Validation;

use BYanelli\Roma\Data\Property;
use BYanelli\Roma\Data\Type;
use BYanelli\Roma\Data\Types;
use Illuminate\Validation\Rule;

readonly class ValidationRules
{
    private array $rules;

    /**
     * @param Property[] $properties
     */
    public function __construct(array $properties)
    {
        $this->rules = $this->getValidationRulesFromProperties($properties);
    }

    private function getTypeValidationRules(Type $type): array
    {
        return match (true) {
            $type instanceof Types\Boolean => ['boolean'],
            $type instanceof Types\Integer => ['integer'],
            $type instanceof Types\Float_ => ['numeric'],
            $type instanceof Types\Date => ['date'],
            $type instanceof Types\String_ => ['string'],
            $type instanceof Types\Array_ => ['array'],
            $type instanceof Types\Enum => [Rule::enum($type->class)],
            default => [],
        };
    }

    private function getValidationRulesFromProperty(Property $property): array
    {
        $result = [];

        [$type, $rules, $key] = [
            $property->type,
            $property->rules,
            $property->getFullKey(),
        ];

        $rules = array_merge($rules, $this->getTypeValidationRules($type));

        if ($property->isRequired) {
            $rules[] = 'required';
        }

        $result[$key] = $rules;

        if ($type instanceof Types\Array_) {
            $result[$key.'.*'] = $this->getTypeValidationRules($type->type);
        }

        return $result;
    }

    /**
     * @param Property[] $properties
     * @return array
     */
    private function getValidationRulesFromProperties(array $properties): array
    {
        return collect($properties)->reduce(function (array $result, Property $property) {
            return array_merge($result, $this->getValidationRulesFromProperty($property));
        }, []);
    }

    public function toArray(): array
    {
        return $this->rules;
    }
}
