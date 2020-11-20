<?php


namespace Rest;


use Closure;
use Rest\Support\Response;
use Rest\Support\ViewResponse;
use FastRoute\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;
use function RingCentral\Psr7\stream_for;

class RequestHandler
{
    protected array $resolvedControllers = [];

    /**
     * @var Application
     */
    protected Application $app;
    /**
     * @var Router
     */
    protected Router $router;

    public function __construct(Application $app, Router $router)
    {
        $this->app    = $app;
        $this->router = $router;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        try {
            $routeInfo = $this->router->dispatch($request->getMethod(), $request->getUri()->getPath());
            switch ($routeInfo[0]) {
                case Dispatcher::NOT_FOUND:
                    return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
                case Dispatcher::METHOD_NOT_ALLOWED:
                    return new Response(405, ['Content-Type' => 'text/plain'], 'Method not allowed');
                case Dispatcher::FOUND:
                    return $this->handleResponse($this->handleRequest($request, $routeInfo));
            }

            throw new RuntimeException('Something went wrong in routing.');
        } catch (Throwable $throwable) {
            return $this->app->exceptionHandler->handle($throwable);
        }

    }

    protected function handleResponse($response)
    {
        if ($response instanceof PromiseInterface) {
            return $response->then(function ($res) {
                return $this->handleResponse($res);
            }, function (Throwable $throwable) {
                return $this->app->exceptionHandler->handle($throwable);
            });
        }

        if ($response instanceof ViewResponse) {
            return $response->withBody(stream_for($response->render()));
        }

        if ($response instanceof Response) {
            return $response;
        }

        if (is_array($response)) {
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));
        }

        return new Response(200, [], $response);
    }

    protected function handleRequest(ServerRequestInterface $request, array $routeInfo)
    {
        $handler = $routeInfo[1];
        $params  = array_values($routeInfo[2]);

        if ($handler instanceof Closure) {
            return $handler($request, ...$params);
        }

        [$fqnClass, $method] = $this->parseHandler($handler);

        $controller = $this->app->make($fqnClass);

        if (!empty($method)) {
            return $controller->{$method}($request, ...$params);
        }

        if (is_callable($controller)) {
            return $controller($request, ...$params);
        }

        throw new RuntimeException(sprintf("Class %s is not invokeable", $fqnClass));
    }

    protected function parseHandler($handler): array
    {
        $fqnClass = '';
        $method   = '';

        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                [$fqnClass, $method] = explode('@', $handler);
            } else {
                $fqnClass = $handler;
            }
        } elseif (is_array($handler) && count($handler) == 2) {
            $fqnClass = $handler[0];
            $method   = $handler[1];
        }

        return [$fqnClass, $method];
    }
}
