<?php

namespace Rest;

use Closure;
use Exception;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use Rest\DI\Container;
use Rest\Exceptions\Handler;
use Rest\Middleware\RequestBodyJsonParserMiddleware;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Application
{
    const VERSION = '1.3.0';

    protected static Application $app;
    protected Container $container;
    public Environment $view;
    public Handler $exceptionHandler;
    protected array $middleware = [];
    protected LoopInterface $loop;
    protected HttpServer $server;
    protected string $listeningUri = '127.0.0.1:3408';
    protected bool $debug = false;
    protected string $viewPath = '';
    protected string $cachePath = '';
    protected Router $router;
    protected Closure $onApplicationBoot;

    public function __construct()
    {
        $this->loop             = Loop::get();
        $this->debug            = env('APP_DEBUG') == 'true';
        $this->middleware       = [
            new RequestBodyJsonParserMiddleware()
        ];
        $this->exceptionHandler = new Handler();
        $this->container        = Container::getInstance();
    }

    public static function getInstance(): self
    {
        return self::$app ??= new static();
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function getEventLoop(): LoopInterface
    {
        return $this->loop;
    }

    public function listen(string $port): self
    {
        $this->listeningUri = $port;
        return $this;
    }

    public function viewPath(string $viewPath): self
    {
        $this->viewPath = $viewPath;
        return $this;
    }

    public function cachePath(string $cachePath): self
    {
        $this->cachePath = $cachePath;
        return $this;
    }

    public function router(Router $router): self
    {
        $this->router = $router;
        return $this;
    }

    public function exceptionHandler(Handler $handler): self
    {
        $this->exceptionHandler = $handler;
        return $this;
    }

    public function addMiddleware(callable $handler): self
    {
        $this->middleware[] = $handler;
        return $this;
    }

    /**
     * @param  string  $bindingName
     * @param  string|null  $contextClassName
     * @return mixed
     * @throws \ReflectionException
     * @throws \Rest\Exceptions\DICannotConstructException
     */
    public function make(string $bindingName, ?string $contextClassName = null): mixed
    {
        return $this->container->resolve($bindingName, $contextClassName);
    }

    public function bind(string $name, $value, ?string $parentName = null): self
    {
        $this->container->bind($name, $value, $parentName);
        return $this;
    }

    public function singleton(string $name, $value, ?string $parentName = null): self
    {
        $this->container->singleton($name, $value, $parentName);
        return $this;
    }

    public function onBoot(Closure $callback): self
    {
        $this->onApplicationBoot = $callback;
        return $this;
    }

    public function run(): void
    {
        $this->view = new Environment(new FilesystemLoader($this->viewPath), [
            'cache' => $this->debug ? false : $this->cachePath.'/views',
            'debug' => $this->debug
        ]);

        $this->middleware[] = new RequestHandler($this, $this->router ?? new Router());

        $this->server = new HttpServer($this->loop, ...$this->middleware);

        $this->server->on('error', function (Exception $e) {
            logger('Error: '.$e->getMessage());
            if ($e->getPrevious() !== null) {
                logger('Previous: '.$e->getPrevious()->getMessage().PHP_EOL.$e->getPrevious()->getTraceAsString());
            }
        });

        $socketServer  = new SocketServer($this->listeningUri, [], $this->loop);
        $serverAddress = str_replace('tcp://', 'http://', $socketServer->getAddress());
        $this->server->listen($socketServer);
        if (isset($this->onApplicationBoot)) {
            call_user_func($this->onApplicationBoot, $this);
        }
        echo "Listening on $serverAddress".PHP_EOL;
    }
}
