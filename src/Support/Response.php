<?php

namespace Rest\Support;

use Psr\Http\Message\StreamInterface;
use React\Http\Io\HttpBodyStream;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7\Response as Psr7Response;
use function RingCentral\Psr7\stream_for;

class Response extends Psr7Response
{
    /**
     * @param  int  $status  HTTP status code (e.g. 200/404)
     * @param  array  $headers  additional response headers
     * @param  string|ReadableStreamInterface|StreamInterface  $body  response body
     * @param  string  $version  HTTP protocol version (e.g. 1.1/1.0)
     * @param ?string  $reason  custom HTTP response phrase
     */
    public function __construct(
        $status = 200,
        array $headers = array(),
        $body = '',
        $version = '1.1',
        $reason = null
    ) {
        if ($body instanceof ReadableStreamInterface && !$body instanceof StreamInterface) {
            $body = new HttpBodyStream($body, null);
        } elseif (!\is_string($body) && !$body instanceof StreamInterface) {
            throw new \InvalidArgumentException('Invalid response body given');
        }

        parent::__construct(
            $status,
            $headers,
            $body,
            $version,
            $reason
        );
    }

    public function stream(ReadableStreamInterface $stream): Response
    {
        return $this->withBody(new HttpBodyStream($stream, null));
    }

    public function json($data, int $status = 200): Response
    {
        return $this
            ->withHeader('Content-Type', 'application/json')
            ->withBody(stream_for(json_encode($data)))
            ->withStatus($status);
    }

    public function redirect(string $to, int $status = 302, array $headers = []): Response
    {
        return $this->withHeader('Location', $to)
            ->withStatus($status)
            ->withHeaders($headers);
    }

    public function withHeaders(array $headers)
    {
        $new = clone $this;
        $new->setHeaders($headers);
        return $new;
    }
}
