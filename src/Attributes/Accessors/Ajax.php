<?php

namespace BYanelli\Roma\Attributes\Accessors;

use Attribute;
use BYanelli\Roma\Attributes\Accessor;
use BYanelli\Roma\Attributes\AttributeTarget;
use BYanelli\Roma\Data\Type;
use BYanelli\Roma\Data\Types\Boolean;
use Illuminate\Http\Request;

/**
 * @see Request::ajax()
 */
#[Attribute(Attribute::TARGET_CLASS|Attribute::TARGET_PARAMETER|Attribute::TARGET_PROPERTY)]
readonly class Ajax extends Accessor
{
    public function __construct(private ?bool $mustBe=null) {}

    public function getRules(AttributeTarget $target): array
    {
        if ($target == AttributeTarget::Class_ && is_null($this->mustBe)) {
            return ['accepted'];
        }

        return match ($this->mustBe) {
            true => ['accepted'],
            false => ['declined'],
            null => [],
        };
    }

    public function getType(): Type
    {
        return new Boolean();
    }
}
