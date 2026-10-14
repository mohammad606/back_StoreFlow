<?php

namespace App\Helpers;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use JsonSerializable;

class ApiResponse implements JsonSerializable
{
    private int $code = Response::HTTP_OK;
    private string $message = 'Success';
    private mixed $data = null;
    private ?array $paginationData = null;

    public function __construct()
    {
        $this->code = Response::HTTP_OK;
        $this->message = 'Success';
        $this->data = null;
        $this->paginationData = null;
    }

    public static function create(): static
    {
        return new static();
    }

    public function ok(): static
    {
        $this->code = Response::HTTP_OK;
        $this->message = $this->message === 'Success' ? 'Success' : $this->message;
        return $this;
    }

    public function unknown(): static
    {
        $this->code = Response::HTTP_INTERNAL_SERVER_ERROR;
        $this->message = $this->message === 'Success' ? 'Something went wrong' : $this->message;
        return $this;
    }

    public function notFound(): static
    {
        $this->code = Response::HTTP_NOT_FOUND;
        $this->message = $this->message === 'Success' ? 'Not found' : $this->message;
        return $this;
    }

    public function badRequest(): static
    {
        $this->code = Response::HTTP_BAD_REQUEST;
        $this->message = $this->message === 'Success' ? 'Bad request' : $this->message;
        return $this;
    }

    public function forbidden(): static
    {
        $this->code = Response::HTTP_FORBIDDEN;
        $this->message = $this->message === 'Success' ? 'Forbidden' : $this->message;
        return $this;
    }

    public function notAuthorized(): static
    {
        $this->code = Response::HTTP_UNAUTHORIZED;
        $this->message = $this->message === 'Success' ? 'Unauthorized' : $this->message;
        return $this;
    }

    public function validationFailure(): static
    {
        $this->code = Response::HTTP_UNPROCESSABLE_ENTITY;
        $this->message = $this->message === 'Success' ? 'Validation failed' : $this->message;
        return $this;
    }

    public function tokenExpiration(): static
    {
        $this->code = Response::HTTP_NOT_ACCEPTABLE;
        $this->message = $this->message === 'Success' ? 'Token expired' : $this->message;
        return $this;
    }

    public function message(?string $message = null): static
    {
        $this->message = $message ?? $this->message;
        return $this;
    }

    public function data(mixed $data = null): static
    {
        $this->data = $data;

        if ($data instanceof LengthAwarePaginator) {
            $this->paginationData = $this->formatPaginateData($data);
            $this->data = $data->items();
        }

        return $this;
    }

    public function paginationData(array $paginationData): static
    {
        $this->paginationData = $paginationData;
        return $this;
    }

    public function noData(mixed $data = null): static
    {
        $this->data = $data;
        $this->message = 'No data found';
        $this->code = Response::HTTP_NOT_FOUND;
        return $this;
    }

    public function jsonSerialize(): array
    {
        $response = [
            'status' => $this->code >= Response::HTTP_BAD_REQUEST ? false : 'success',
            'message' => $this->message,
            'data' => $this->data,
            'code' => $this->code,
        ];

        if ($this->paginationData !== null) {
            $response['pagination'] = $this->paginationData;
        }

        return $response;
    }

    public function getSuccess(): static
    {
        $this->message = 'Data retrieved successfully';
        return $this;
    }

    public function storeSuccess(): static
    {
        $this->message = 'Data stored successfully';
        return $this;
    }

    public function updateSuccess(): static
    {
        $this->message = 'Data updated successfully';
        return $this;
    }

    public function deleteSuccess(): static
    {
        $this->message = 'Data deleted successfully';
        return $this;
    }

    public function paginatedSuccessfully(mixed $data, array $paginationData): JsonResponse
    {
        return $this->ok()
            ->getSuccess()
            ->data($data)
            ->paginationData($paginationData)
            ->send();
    }

    public function createdSuccessfully(mixed $data = null): JsonResponse
    {
        return $this->ok()
            ->storeSuccess()
            ->data($data)
            ->send();
    }

    public function getSuccessfully(mixed $data = null): JsonResponse
    {
        return $this->ok()
            ->getSuccess()
            ->data($data)
            ->send();
    }

    public function updatedSuccessfully(mixed $data = null): JsonResponse
    {
        return $this->ok()
            ->updateSuccess()
            ->data($data)
            ->send();
    }

    public function deleteSuccessfully(mixed $data = true): JsonResponse
    {
        return $this->ok()
            ->deleteSuccess()
            ->data($data)
            ->send();
    }

    public function unknownError(): static
    {
        $this->message = 'Something went wrong';
        $this->code = Response::HTTP_INTERNAL_SERVER_ERROR;
        return $this;
    }

    public function send(): JsonResponse
    {
        return response()->json($this, $this->code);
    }

    public static function success($data = null, $message = 'Success', $pagination = null): JsonResponse
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'code' => Response::HTTP_OK,
        ];

        if ($data instanceof LengthAwarePaginator) {
            $response['data'] = $data->items();
            $response['pagination'] = self::buildPagination($data);
        }

        if ($pagination !== null) {
            $response['pagination'] = self::buildPagination($pagination);
        }

        return response()->json($response, Response::HTTP_OK);
    }

    public static function error($message = 'Error', $code = Response::HTTP_BAD_REQUEST, $data = []): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => $data,
            'code' => $code,
        ], $code);
    }

    public static function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'Validation Error',
            'errors' => $errors,
            'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function when($condition, Closure $then, ?Closure $else = null): static
    {
        if (is_callable($condition)) {
            $condition = $condition($this);
        }

        if ($condition) {
            return $then($this);
        }

        if ($else) {
            return $else($this);
        }

        return $this;
    }

    public function formatPaginateData(LengthAwarePaginator $data): array
    {
        return self::buildPagination($data);
    }

    protected static function buildPagination(LengthAwarePaginator $data): array
    {
        return [
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'last_page' => $data->lastPage(),
            'next_page_url' => $data->nextPageUrl(),
            'prev_page_url' => $data->previousPageUrl(),
        ];
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return self::create()->{$name}(...$arguments);
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this, $name)) {
            return $this->{$name}(...$arguments);
        }

        return $this;
    }
}
