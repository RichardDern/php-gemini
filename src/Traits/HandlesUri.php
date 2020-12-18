<?php

declare(strict_types = 1);

namespace RichardDern\Gemini\Traits;

use League\Uri\Exceptions\SyntaxError;
use League\Uri\Uri;
use League\Uri\UriInfo;
use League\Uri\UriResolver;
use RichardDern\Gemini\Exceptions\MissingBaseUriException;

/**
 * Base code for classes working with URIs.
 */
trait HandlesUri
{
    /**
     * Ensure a given URI is really a Uri object.
     *
     * @param mixed $uri
     * @param bool  $couldBeRelative Indicates if specified URI could be relative
     *
     * @throws League\Uri\Exceptions\SyntaxError
     *
     * @return \League\Uri\Uri
     */
    protected function parseUri($uri, $couldBeRelative = false)
    {
        if ($couldBeRelative) {
            return $this->resolveRelativeUri($uri);
        }
        if (\is_a($uri, \League\Uri\Uri::class)) {
            return $uri;
        }

        if (\is_string($uri)) {
            return Uri::createFromString($uri);
        }

        throw new SyntaxError('Specified uri is invalid');
    }

    /**
     * Resolves specified URI against base URI.
     *
     * @param \League\Uri\Uri|string $uri
     *
     * @throws RichardDern\Gemini\Exceptions\MissingBaseUriException
     * @throws League\Uri\Exceptions\SyntaxError
     *
     * @return \League\Uri\Uri
     */
    protected function resolveRelativeUri($uri)
    {
        $uri = $this->parseUri($uri, false);

        if (UriInfo::isAbsolute($uri)) {
            return $uri;
        }

        if (empty($this->baseUri)) {
            throw new MissingBaseUriException();
        }

        return UriResolver::resolve($uri, $this->baseUri);
    }

    /**
     * Ensure specified URI is valid, as defined by the Gemini specs.
     *
     * @param mixed $uri
     *
     * @throws League\Uri\Exceptions\SyntaxError
     */
    protected function validateUri($uri)
    {
        if (!empty($uri->getUserInfo())) {
            throw new SyntaxError('userinfo sub-component is not allowed');
        }

        if (empty($uri->getHost())) {
            throw new SyntaxError('host sub-component is required');
        }

        if (mb_strlen((string) $uri, 'UTF-8') > 1024) {
            throw new SyntaxError('uri is too long');
        }
    }
}
