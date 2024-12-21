<?php

namespace BYanelli\Roma;

use BackedEnum;
use BYanelli\Roma\Properties\Property;
use BYanelli\Roma\Properties\Source;
use BYanelli\Roma\Properties\Types;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Str;
use RuntimeException;
use UnitEnum;

class RequestData implements Arrayable
{
    private array $data;

    /**
     * @param Request $request
     * @param Property[] $properties
     * @param DateFactory $dateFactory
     */
    public function __construct(
        private readonly Request $request,
        private readonly array $properties,
        private readonly DateFactory $dateFactory = new DateFactory,
    ) {
        $this->data = $this->flattenRequest();
        $this->addRequestObjectValuesToData();
        $this->castData();
    }

    private function flattenRequest(): array
    {
        return [
            'input' => $this->request->input(),
            'query' => $this->request->query->all(),
            'header' => $this->request->server->getHeaders(),
            'body' => $this->request->isJson()
                ? $this->request->json()->all()
                : $this->request->request->all(),
        ];
    }

    private function getAccessKey(Property $property): string
    {
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

    private function toBoolean(string $val): bool
    {
        return ($val == 'true')
            ? true
            : (($val == 'false')
                ? false
                : throw new RuntimeException("Invalid boolean: $val"));
    }

    private function toInteger(string $val): int
    {
        return (is_numeric($val) && !str_contains($val, '.'))
            ? intval($val)
            : throw new RuntimeException("Invalid integer: $val");
    }

    private function toFloat(string $val): float
    {
        return is_numeric($val)
            ? floatval($val)
            : throw new RuntimeException("Invalid integer: $val");
    }

    private function toEnum(Types\Enum $type, string $val): mixed
    {
        /** @var class-string<BackedEnum|UnitEnum> $class */
        $class = $type->class;

        $reflectionClass = new \ReflectionEnum($class);
        $backed = $reflectionClass->isBacked();
        $backingType = $reflectionClass->getBackingType()?->getName();

        return match (true) {
            $backed && $backingType == 'int' => $class::from(intval($val)),
            $backed && $backingType == 'string' => $class::from($val),
            default => collect($class::cases())->firstOrFail(fn(UnitEnum $enum) => $enum->name == $val),
        };
    }

    private function castData(): void
    {
        foreach ($this->properties as $property) {
            [$type, $key] = [$property->type, $this->getAccessKey($property)];

            if ($type instanceof Types\Mixed_) { continue; }

            if (!Arr::has($this->data, $key)) { continue; }

            $rawValue = Arr::get($this->data, $key);

            try {
                $typedValue = match (true) {
                    $type instanceof Types\Boolean => $this->toBoolean($rawValue),
                    $type instanceof Types\Integer => $this->toInteger($rawValue),
                    $type instanceof Types\Float_ => $this->toFloat($rawValue),
                    $type instanceof Types\Date => $this->dateFactory->parse($rawValue),
                    $type instanceof Types\String_ => $rawValue,
                    $type instanceof Types\Enum => $this->toEnum($type, $rawValue),
                    default => throw new RuntimeException('Unsupported type: '.$type::class),
                };
            } catch (\Exception|\ValueError $e) {
                $typedValue = $rawValue;
            }

            Arr::set($this->data, $key, $typedValue);
        }
    }

    private function addRequestObjectValuesToData(): void
    {
        foreach ($this->properties as $property) {
            if ($property->source != Source::Object) { continue; }

            $key = $this->getAccessKey($property);
            $value = call_user_func($property->accessor, $this->request);

            Arr::set($this->data, $key, $value);
        }
    }

    public function getValue(Property $property)
    {
        return Arr::get($this->data, $this->getAccessKey($property), $property->default);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
