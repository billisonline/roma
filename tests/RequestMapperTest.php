<?php

use BYanelli\Roma\Attributes\Accessors\Ajax;
use BYanelli\Roma\Attributes\Accessors\Method;
use BYanelli\Roma\Attributes\Header;
use BYanelli\Roma\Attributes\Headers\ContentType;
use BYanelli\Roma\Attributes\Rule;
use Illuminate\Validation\ValidationException;

trait HasQuantity {
    #[Rule('gt:9')]
    public readonly int $quantity;
}

readonly class TestRequest
{
    use HasQuantity;

    public function __construct(
        public string $url,
        public string $name,
        public float $price,
        #[Ajax(allowed: true)]
        public bool $isAjax,
        #[Method]
        public string $method,
    ) {}

    public \DateTimeInterface $date;
    public bool $flag;

    #[Header('X-Flag')]
    public bool $flagFromHeader;

    #[ContentType]
    public string $contentType;
}

it('maps requests', function () {
    $this->bindRequest(
        query: [
            'url' => 'https://example.com',
            'name' => 'John Doe',
            'price' => '9.99',
            'quantity' => '10',
            'date' => '2024-01-01',
            'flag' => 'true',
        ],
        headers: [
            'X-Flag' => 'false',
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/json',
        ],
    );

    $request = $this->getRequestMapper()->mapRequest(TestRequest::class);

    $this->assertEquals('https://example.com', $request->url);
    $this->assertEquals('John Doe', $request->name);
    $this->assertEquals('2024-01-01', $request->date->format('Y-m-d'));
    $this->assertEquals(9.99, $request->price);
    $this->assertEquals(10, $request->quantity);
    $this->assertEquals(true, $request->flag);
    $this->assertEquals(false, $request->flagFromHeader);
    $this->assertEquals('application/json', $request->contentType);
    $this->assertEquals(true, $request->isAjax);
    $this->assertEquals('GET', $request->method);
});

it('fails to map invalid requests', function () {
    $this->bindRequest(
        query: [
            'url' => 'https://example.com',
            'name' => 'John Doe',
            'price' => '9.99.9',
            'quantity' => '8',
            'date' => 'jijiji',
            'flag' => 'truee',
        ],
        headers: [
            'X-Flag' => 'falsee',
            'Content-Type' => 'application/json',
        ],
    );

    try {
        $this->getRequestMapper()->mapRequest(TestRequest::class);
    } catch (ValidationException $e) {
        $this->assertEquals([
            'input.price' => [
                'The input.price field must be a number.'
            ],
            'request.ajax' => [
                'The request.ajax field must be accepted.'
            ],
            'input.quantity' => [
                'The input.quantity field must be greater than 9.'
            ],
            'input.date' => [
                'The input.date field must be a valid date.'
            ],
            'input.flag' => [
                'The input.flag field must be true or false.'
            ],
            'header.X_FLAG' => [
                // todo: weird message?
                'The header. x  f l a g field must be true or false.'
            ],
        ], $e->errors());

        return;
    }

    $this->assertTrue(false, 'Exception was not thrown');
});
