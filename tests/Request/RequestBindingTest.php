<?php /** @noinspection PhpIllegalPsrClassPathInspection */

use BYanelli\Roma\Tests\TestCase;
use BYanelli\Roma\Request\ContextualBinding\Request;

class TestBoundRequest {
    public function __construct(
        public string $a,
        public string $b,
    ) {}
}

it('binds the request contextually', function () {
    /** @var TestCase $this */
    $this->setRequest(
        query: [
            'a' => 'foo',
            'b' => 'bar',
        ],
    );

    $func = function (#[Request] TestBoundRequest $request) {
        $this->assertEquals('foo', $request->a);
        $this->assertEquals('bar', $request->b);
    };

    $this->app->call($func);
});
