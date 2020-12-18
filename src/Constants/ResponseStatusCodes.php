<?php

declare(strict_types = 1);

namespace RichardDern\Gemini\Constants;

/**
 * Response codes.
 */
class ResponseStatusCodes
{
    public const INPUT = 10;

    public const SENSITIVE_INPUT = 11;

    public const SUCCESS = 20;

    public const TEMPORARY_REDIRECT = 30;

    public const PERMANENT_REDIRECT = 31;

    public const TEMPORARY_FAILURE = 40;

    public const SERVER_UNAVAILABLE = 41;

    public const CGI_ERROR = 42;

    public const PROXY_ERROR = 43;

    public const SLOW_DOWN = 44;

    public const PERMANENT_FAILURE = 50;

    public const NOT_FOUND = 51;

    public const GONE = 52;

    public const PROXY_REQUEST_REFUSED = 53;

    public const BAD_REQUEST = 59;

    public const CLIENT_CERTIFICATE_REQUIRED = 60;

    public const CERTIFICATE_NOT_AUTHORIZED = 61;

    public const CERTIFICATE_NOT_VALID = 62;
}
