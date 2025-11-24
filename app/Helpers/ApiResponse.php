<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($data = null, $message = 'Success', $pagination = null)
    {
        $response = [
            'status' => 'success',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($pagination !== null) {
            $response['pagination'] = [
                'current_page' => $pagination->currentPage(),
                'per_page' => $pagination->perPage(),
                'total' => $pagination->total(),
                'last_page' => $pagination->lastPage(),
                'next_page_url' => $pagination->nextPageUrl(),
                'prev_page_url' => $pagination->previousPageUrl(),
            ];
        }

        return response()->json($response);
    }

    public static function error($message = 'Error', $code = 400, $data = [])
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => $data
        ], $code);
    }
}
