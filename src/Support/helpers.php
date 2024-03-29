<?php

use Rest\Application;
use Rest\Exceptions\DumpDieException;
use Rest\Exceptions\HttpAbortException;
use Rest\Support\Response;
use Rest\Support\ViewResponse;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

if (!function_exists('app')) {
    /**
     * @param  string|null  $class
     * @param  string|null  $parentClass
     * @return mixed
     * @throws \ReflectionException
     * @throws \Rest\Exceptions\DICannotConstructException
     */
    function app(?string $class = null, ?string $parentClass = null): mixed
    {
        if ($class) {
            $parentClass ??= debug_backtrace(false)[1]['class'] ?? null;
            return Application::getInstance()->make($class, $parentClass);
        }

        return Application::getInstance();
    }
}

if (!function_exists('view')) {
    function view(string $viewName, array $data = []): ViewResponse
    {
        return (new ViewResponse())
            ->withView($viewName)
            ->withData($data);
    }
}

if (!function_exists('response')) {
    function response(): Response
    {
        return new Response();
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('dd')) {
    /**
     * @param  mixed  ...$vars
     * @throws DumpDieException|ErrorException
     */
    function dd(...$vars)
    {
        $cloner = new VarCloner();
        $output = fopen('php://memory', 'r+b');
        $dumper = new HtmlDumper($output);

        foreach ($vars as $var) {
            $dumper->dump($cloner->cloneVar($var), $output);
        }

        throw new DumpDieException(stream_get_contents($output, -1, 0));
    }
}


if (!function_exists('abort')) {
    /**
     * @param  int  $status
     * @param  array|string  $message
     * @throws \Rest\Exceptions\HttpAbortException
     */
    function abort(int $status = 500, array|string $message = 'Internal Server Error')
    {
        throw new HttpAbortException(is_array($message) ? json_encode($message) : $message, $status);
    }
}

if (!function_exists('abort_if')) {
    /**
     * @param  bool  $condition
     * @param  int  $status
     * @param  mixed  $message
     * @throws HttpAbortException
     */
    function abort_if(bool $condition, int $status = 500, array|string $message = 'Internal Server Error'): void
    {
        if ($condition) {
            abort($status, $message);
        }
    }
}

if (!function_exists('abort_unless')) {
    /**
     * @param  bool  $condition
     * @param  int  $status
     * @param  mixed  $message
     * @throws \Rest\Exceptions\HttpAbortException
     */
    function abort_unless(bool $condition, int $status = 500, array|string $message = 'Internal Server Error'): void
    {
        abort_if(!$condition, $status, $message);
    }
}

if (!function_exists('logger')) {
    function logger($message): void
    {
        if ($message instanceof Throwable) {
            $message = $message->getMessage().PHP_EOL.$message->getTraceAsString();
        }
        printf("[%s] $message".PHP_EOL, date('Y-m-d H:i:s'));
    }
}

if (!function_exists('deleteDir')) {
    function deleteDir(string $dir, $preserve = false): void
    {
        foreach (glob("$dir/*") as $item) {
            is_dir($item) ? deleteDir($item) : unlink($item);
        }

        if (!$preserve) {
            @rmdir($dir);
        }
    }
}
