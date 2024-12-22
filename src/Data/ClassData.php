<?php

namespace BYanelli\Roma\Data;

use BackedEnum;
use BYanelli\Roma\Data\Sources\Body;
use BYanelli\Roma\Data\Sources\Header;
use BYanelli\Roma\Data\Sources\Input;
use BYanelli\Roma\Data\Sources\Object_;
use BYanelli\Roma\Data\Sources\Property as PropertySource;
use BYanelli\Roma\Data\Sources\Query;
use BYanelli\Roma\Data\Types\Class_;
use BYanelli\Roma\RequestData;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Str;
use RuntimeException;
use UnitEnum;

class ClassData
{
    private array $data;

    public function __construct(
        private readonly Request     $request,
        private readonly Class_      $class,
        private readonly ?Source     $source = null,
        ?array                       $data = null,
        private readonly DateFactory $dateFactory = new DateFactory,
    ) {
        if ($data == null) {
            $this->data = $this->flattenRequest();
            $this->addRequestObjectValuesToData();
        } else {
            $this->data = $data;
        }

        $this->castData();
    }

    private function flattenRequest(): array
    {
        return [
            (new Input)->getKey() => $this->request->input(),
            (new Query)->getKey() => $this->request->query->all(),
            (new Header)->getKey() => $this->request->server->getHeaders(),
            (new Body)->getKey() => $this->request->isJson()
                ? $this->request->json()->all()
                : $this->request->request->all(),
        ];
    }

    public function getConstructorValues(): array
    {
        return collect($this->class->properties)
            ->filter(fn(Property $p) => $p->role == Role::Constructor)
            ->map($this->getValue(...))
            ->all();
    }

    public function getClassProperties(): array
    {
        return collect($this->class->properties)
            ->filter(fn(Property $p) => $p->role == Role::Property)
            ->mapWithKeys(fn(Property $p) => [$p->name => $this->getValue($p)])
            ->all();
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
        foreach ($this->class->properties as $property) {
            [$type, $key] = [$property->type, $this->getKey($property)];

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
                    $type instanceof Types\Class_ => $this->toRequestData2($property, $type, $rawValue),
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
        foreach ($this->class->properties as $property) {
            if (get_class($property->source->parent) != Object_::class) { continue; }

            $value = call_user_func($property->accessor, $this->request);

            Arr::set(
                $this->data,
                $property->getFullKey() /*todo: get own key, always go back to first level?*/,
                $value
            );
        }
    }

    private function getKey(Property $property): string
    {
        return ($this->source != null)
            ? Str::after($property->getFullKey(), $this->source->getKey().'.')
            : $property->getFullKey();
    }

    public function getValue(Property $property)
    {
        return Arr::get($this->data, $this->getKey($property), $property->default);
    }

    public function toArray(): array
    {
        return collect($this->data)
            ->mapWithKeys(fn($val, $key) => [
                $key => $val instanceof ClassData ? $val->toArray() : $val
            ])
            ->all();
    }

    private function toRequestData2(
        Property $property,
        Class_ $class_,
        array $data
    ): ClassData {
        return new ClassData(
            $this->request,
            $class_,
            $property->source,
            $data,
            $this->dateFactory
        );
    }

    public function getClassName(): string
    {
        return $this->class->class;
    }
}
