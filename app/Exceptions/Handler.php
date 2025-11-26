<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Helpers\ApiResponse;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    public function render($request, Throwable $e)
    {
        if ($e instanceof ModelNotFoundException) {
            return ApiResponse::error('Record not found', 404);
        }
        if ($e instanceof NotFoundHttpException) {
            return ApiResponse::error('Route not found', 404);
        }

        return parent::render($request, $e);
    }
}
