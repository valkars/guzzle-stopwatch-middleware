<?php

/*
 * This file is part of the CsaGuzzleBundle package
 *
 * (c) Charles Sarrazin <charles@sarraz.in>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Csa\Tests\GuzzleHttp\Middleware\Stopwatch;

use Csa\GuzzleHttp\Middleware\Stopwatch\StopwatchMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;

class StopwatchMiddlewareTest extends TestCase
{
    public function testSynchronousRequest(): void
    {
        $response = new Response(204);
        $mocks = \array_fill(0, 3, $response);
        $mock = new MockHandler($mocks);
        $handler = HandlerStack::create($mock);

        $stopwatch = new Stopwatch();

        $handler->push(new StopwatchMiddleware($stopwatch));

        $client = new Client(['handler' => $handler]);

        $client->get('https://foo.bar');
        $this->assertContains('GET https://foo.bar', \array_keys($stopwatch->getSectionEvents('__root__')));
    }

    public function testSinglePromise(): void
    {
        $response = new Response(204);
        $mock = new MockHandler([$response]);
        $handler = HandlerStack::create($mock);

        $stopwatch = new Stopwatch();

        $handler->push(new StopwatchMiddleware($stopwatch));

        $client = new Client(['handler' => $handler]);

        $client->postAsync('https://foo.bar');

        $this->assertContains('POST https://foo.bar', \array_keys($stopwatch->getSectionEvents('__root__')));
    }

    public function testMultiplePromises(): void
    {
        $response = new Response(204);
        $mocks = \array_fill(0, 3, $response);
        $mock = new MockHandler($mocks);
        $handler = HandlerStack::create($mock);

        $stopwatch = new Stopwatch();

        $handler->push(new StopwatchMiddleware($stopwatch));

        $client = new Client(['handler' => $handler]);

        $promises = [
            'foo' => $client->getAsync('https://foo.bar'),
            'bar' => $client->getAsync('https://foo.bar'),
            'baz' => $client->getAsync('https://foo.bar'),
        ];

        Utils::unwrap($promises);

        for ($i = 1; $i <= 3; ++$i) {
            $this->assertContains(
                $i > 1 ? \sprintf('GET https://foo.bar (%s)', $i) : 'GET https://foo.bar',
                \array_keys($stopwatch->getSectionEvents('__root__'))
            );
        }
    }
}
