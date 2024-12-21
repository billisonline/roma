<?php

namespace BYanelli\Roma;

use BYanelli\Roma\Properties\Property;
use BYanelli\Roma\Properties\Source;
use BYanelli\Roma\Properties\Type;
use BYanelli\Roma\Properties\Types;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            $this->getAccessKey($property),
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
