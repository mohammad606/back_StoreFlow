<?php

use App\Helpers\ApiResponse;
use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\EnsureAccountAccess;
use App\Http\Middleware\EnsureRepresentativeOwnerAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => CheckAdminRole::class,
            'account.access' => EnsureAccountAccess::class,
            'representative.access' => EnsureRepresentativeOwnerAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Exception $exception, Request $request) {
            if ($exception instanceof ValidationException) {
                return ApiResponse::validationError($exception->errors());
            }

            if ($exception instanceof ModelNotFoundException) {
                return ApiResponse::error(
                    "{$exception->getModel()::make()->getTable()} not found",
                    Response::HTTP_NOT_FOUND
                );
            }
        });
    })->create();
