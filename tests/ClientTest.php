<?php

declare(strict_types = 1);
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class ClientTest extends TestCase
{
    public function testCanSetStringUri(): void
    {
        $client = new RichardDern\Gemini\Client('gemini.circumlunar.space');
        $client->setBaseUri('gemini://gemini.circumlunar.space/software/');

        $this->assertInstanceOf(
            League\Uri\Uri::class,
            $client->getBaseUri()
        );
    }

    public function testCanSetUriObject(): void
    {
        $client = new RichardDern\Gemini\Client('gemini.circumlunar.space');
        $uri    = League\Uri\Uri::createFromString('gemini://gemini.circumlunar.space/software/');
        $client->setBaseUri($uri);

        $this->assertInstanceOf(
            League\Uri\Uri::class,
            $client->getBaseUri()
        );
    }

    public function testThrowsInvalidUriExceptionFromArray(): void
    {
        $this->expectException(League\Uri\Exceptions\SyntaxError::class);

        $client = new RichardDern\Gemini\Client('gemini.circumlunar.space');
        $client->setBaseUri(['malformed url']);
    }

    public function testResolvesRelativeUri(): void
    {
        $client = new RichardDern\Gemini\Client('gemini.circumlunar.space');
        $client->setBaseUri('gemini://gemini.circumlunar.space');
        $client->request('/software/');

        $this->assertEquals(
            (string) $client->getRequestedUri(),
            'gemini://gemini.circumlunar.space:1965/software/'
        );
    }

    public function testMissingSchemeUri(): void
    {
        $client = new RichardDern\Gemini\Client('gemini.circumlunar.space');
        $client->request('gemini.circumlunar.space/software/');

        $this->assertEquals(
            (string) $client->getRequestedUri(),
            'gemini://gemini.circumlunar.space:1965/gemini.circumlunar.space/software/'
        );
    }

    public function testThrowsMissingBaseUriException(): void
    {
        $this->expectException(RichardDern\Gemini\Exceptions\MissingBaseUriException::class);

        $client = new RichardDern\Gemini\Client();
        $client->request('/software/');
    }

    public function testNotThrowsMissingBaseUriExceptionWithAbsoluteUri(): void
    {
        $client = new RichardDern\Gemini\Client();
        $client->request('gemini://gemini.circumlunar.space/software/');

        $this->assertEquals(
            (string) $client->getRequestedUri(),
            'gemini://gemini.circumlunar.space/software/'
        );
    }
}
