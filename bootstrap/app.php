<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Laravel only treats a cache path as absolute when it starts with / or \.
// NativePHP points the packaged caches at the user-data folder. On Windows
// that path is C:\..., so without a drive prefix it is appended to the
// install directory and the app quits during boot.
foreach (range('A', 'Z') as $drive) {
    $app->addAbsoluteCachePathPrefix($drive.':');
}

return $app;
