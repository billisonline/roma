<?php

namespace BYanelli\Roma;

use BackedEnum;
use BYanelli\Roma\Properties\Property;
use BYanelli\Roma\Properties\Source;
use BYanelli\Roma\Properties\Types\Boolean;
use BYanelli\Roma\Properties\Types\Date;
use BYanelli\Roma\Properties\Types\Enum;
use BYanelli\Roma\Properties\Types\Float_;
use BYanelli\Roma\Properties\Types\Integer;
use BYanelli\Roma\Properties\Types\Mixed_;
use BYanelli\Roma\Properties\Types\String_;
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
        $this->castData();
        $this->addRequestObjectValuesToData();
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

    private function toEnum(Enum $type, string $val): mixed
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
            [$source, $type, $key] = [$property->source, $property->type, $this->getAccessKey($property)];

            if ($source == Source::Object) { continue; }
            if ($type instanceof Mixed_) { continue; }

            if (!Arr::has($this->data, $key)) { continue; }

            $rawValue = Arr::get($this->data, $key);

            try {
                $typedValue = match (true) {
                    $type instanceof Boolean => $this->toBoolean($rawValue),
                    $type instanceof Integer => $this->toInteger($rawValue),
                    $type instanceof Float_ => $this->toFloat($rawValue),
                    $type instanceof Date => $this->dateFactory->parse($rawValue),
                    $type instanceof String_ => $rawValue,
                    $type instanceof Enum => $this->toEnum($type, $rawValue),
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
