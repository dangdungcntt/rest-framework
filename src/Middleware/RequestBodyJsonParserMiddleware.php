<?php


namespace Rest\Middleware;


use Psr\Http\Message\ServerRequestInterface;

class RequestBodyJsonParserMiddleware
{
    public function __invoke(ServerRequestInterface $request, $next)
    {
        $type = strtolower($request->getHeaderLine('Content-Type'));
        list ($type) = explode(';', $type);

        if ($type === 'application/json') {
            return $next($this->parseBodyJson($request));
        }

        return $next($request);
    }

    protected function parseBodyJson(ServerRequestInterface $request)
    {
        return $request->withParsedBody(@json_decode((string)$request->getBody(), true));
    }
}
