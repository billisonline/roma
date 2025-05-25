<?php

namespace BYanelli\Roma\Data;

use BackedEnum;
use BYanelli\Roma\Data\Sources\Body;
use BYanelli\Roma\Data\Sources\Header;
use BYanelli\Roma\Data\Sources\Input;
use BYanelli\Roma\Data\Sources\RequestObject_;
use BYanelli\Roma\Data\Sources\Query;
use BYanelli\Roma\Data\Types\Class_;
use BYanelli\Roma\Validation\ValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Str;
use RuntimeException;
use UnitEnum;

class ClassRequestMapping
{
    private array $data;

    public function __construct(
        private readonly Class_      $class,
        private readonly Request     $request,
        private readonly ?Source     $source = null,
        ?array                       $data = null,
        private readonly DateFactory $dateFactory = new DateFactory,
    ) {
        if ($data == null) {
            $this->data = $this->flattenRequest();
            $this->addRequestObjectValuesToData();
            $this->addValidationOnlyValuesToData();
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
            (new Header)->getKey() => collect($this->request->server->getHeaders())
                ->mapWithKeys(fn($val, $key) => [Str::lower($key) => $val])
                ->all(),
            (new Body)->getKey() => $this->request->isJson()
                ? $this->request->json()->all()
                : $this->request->request->all(),
        ];
    }

    /**
     * @return list<Property>
     */
    private function getConstructorProperties(): array
    {
        return collect($this->class->properties)
            ->filter(fn(Property $p) => $p->role == Role::Constructor)
            ->all();
    }

    /**
     * @return list<Property>
     */
    private function getClassProperties(): array
    {
        return collect($this->class->properties)
            ->filter(fn(Property $p) => $p->role == Role::Property)
            ->all();
    }

    /**
     * @return list<Property>
     */
    private function getValidationOnlyProperties(): array
    {
        return collect($this->class->properties)
            ->filter(fn(Property $p) => $p->role == Role::ValidationOnly)
            ->all();
    }

    /**
     * @return list<mixed>
     */
    public function getConstructorValuesArray(): array
    {
        return Arr::map($this->getConstructorProperties(), $this->getValue(...));
    }

    /**
     * @return array<string, mixed>
     */
    public function getClassPropertiesMap(): array
    {
        return Arr::mapWithKeys($this->getClassProperties(), fn(Property $p) => [$p->name => $this->getValue($p)]);
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
            [$role, $type, $key] = [$property->role, $property->type, $this->getKey($property)];

            if ($role == Role::ValidationOnly) { continue; }

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
                    $type instanceof Types\Class_ => $this->toNestedClass($property, $type, $rawValue),
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
            if (get_class($property->source->parent) != RequestObject_::class) { continue; }

            $value = call_user_func($property->accessor, $this->request);

            Arr::set(
                $this->data,
                $property->getFullKey() /*todo: get own key, always go back to first level?*/,
                $value
            );
        }
    }

    private function addValidationOnlyValuesToData(): void
    {
        foreach ($this->getValidationOnlyProperties() as $property) {
            Arr::set(
                $this->data,
                '__request.'.$property->getFullKey(),
                Arr::get($this->data, $property->getFullKey()),
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
                $key => $val instanceof ClassRequestMapping ? $val->toArray() : $val
            ])
            ->all();
    }

    private function toNestedClass(
        Property $property,
        Class_ $class_,
        array $data
    ): ClassRequestMapping {
        return new ClassRequestMapping(
            $class_,
            $this->request,
            $property->source,
            $data,
            $this->dateFactory
        );
    }

    public function getClassName(): string
    {
        return $this->class->class;
    }

    public function rules(): array
    {
        return new ValidationRules($this->class)->toArray();
    }

    public function data(): array
    {
        return $this->toArray();
    }
}
