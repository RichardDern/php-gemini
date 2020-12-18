<?php

namespace RichardDern\Gemini;

/**
 * Represents a Gemini server response.
 */
class Response
{
    /**
     * Status code.
     *
     * @var int
     */
    public $status;

    /**
     * Meta (MIME type).
     *
     * @var string
     */
    public $meta;

    /**
     * Document's body.
     *
     * @var string
     */
    public $body;

    public function __construct($status, $meta, $body = null)
    {
        $this->status = $status;
        $this->meta   = $meta;
        $this->body   = $body;
    }
}
