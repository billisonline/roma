<?php

use BYanelli\Roma\Attributes\ContentType;
use BYanelli\Roma\Attributes\Header;
use Illuminate\Validation\ValidationException;

readonly class TestRequest
{
    public function __construct(
        public string $url,
        public string $name,
        public float $price,
    ) {}

    public int $quantity;
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
});

it('fails to map invalid requests', function () {
    $this->bindRequest(
        query: [
            'url' => 'https://example.com',
            'name' => 'John Doe',
            'price' => '9.99.9',
            'quantity' => '10x',
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
            'input.quantity' => [
                'The input.quantity field must be an integer.'
            ],
            'input.date' => [
                'The input.date field must be a valid date.'
            ],
            'input.flag' => [
                'The input.flag field must be true or false.'
            ],
            'header.X_FLAG' => [
                'The header. x  f l a g field must be true or false.'
            ],
        ], $e->errors());

        return;
    }

    $this->assertTrue(false, 'Exception was not thrown');
});
