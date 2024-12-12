<?php

use BYanelli\Roma\RequestMapper;

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
}



it('maps requests', function () {

    $this->bindRequest(query: [
        'url' => 'https://example.com',
        'name' => 'John Doe',
        'price' => 9.99,
        'quantity' => 10,
        'date' => '2024-01-01',
        'flag' => 'true',
    ]);

    $request = $this->getRequestMapper()->mapRequest(TestRequest::class);

    $this->assertEquals('https://example.com', $request->url);
    $this->assertEquals('John Doe', $request->name);
    $this->assertEquals('2024-01-01', $request->date->format('Y-m-d'));
    $this->assertEquals(9.99, $request->price);
    $this->assertEquals(10, $request->quantity);
    $this->assertEquals(true, $request->flag);
});
