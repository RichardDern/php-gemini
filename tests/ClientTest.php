<?php

declare(strict_types = 1);
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class ClientTest extends TestCase
{
    private $testServer ='127.0.0.1';

    public function testCanSetStringUri(): void
    {
        $client = new RichardDern\Gemini\Client($this->testServer);
        $client->setBaseUri('gemini://'.$this->testServer.'/software/');

        $this->assertInstanceOf(
            League\Uri\Uri::class,
            $client->getBaseUri()
        );
    }

    public function testCanSetUriObject(): void
    {
        $client = new RichardDern\Gemini\Client($this->testServer);
        $uri    = League\Uri\Uri::createFromString('gemini://'.$this->testServer.'/software/');
        $client->setBaseUri($uri);

        $this->assertInstanceOf(
            League\Uri\Uri::class,
            $client->getBaseUri()
        );
    }

    public function testThrowsInvalidUriExceptionFromArray(): void
    {
        $this->expectException(League\Uri\Exceptions\SyntaxError::class);

        $client = new RichardDern\Gemini\Client($this->testServer);
        $client->setBaseUri(['malformed url']);
    }

    public function testResolvesRelativeUri(): void
    {
        $client = new RichardDern\Gemini\Client($this->testServer);
        $client->setBaseUri('gemini://'.$this->testServer);
        $client->request('/software/');

        $this->assertEquals(
            (string) $client->getRequestedUri(),
            'gemini://'.$this->testServer.':1965/software/'
        );
    }

    public function testMissingSchemeUri(): void
    {
        $client = new RichardDern\Gemini\Client($this->testServer);
        $client->request($this->testServer.'/software/');

        $this->assertEquals(
            (string) $client->getRequestedUri(),
            'gemini://'.$this->testServer.':1965/'.$this->testServer.'/software/'
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
        $client->request('gemini://'.$this->testServer.'/software/');

        $this->assertEquals(
            (string) $client->getRequestedUri(),
            'gemini://'.$this->testServer.'/software/'
        );
    }
}
