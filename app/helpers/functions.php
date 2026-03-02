<?php

use Illuminate\Support\Arr;

function successResponse($data, $message = 'Success', $status = 200, $extra = null)
{
    return response()->json([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'extra' => $extra,
    ], $status);
}

function errorResponse($message = 'Error', $status = 400)
{
    return response()->json([
        'success' => false,
        'message' => $message,
    ], $status);
}

function locale()
{
    return app()->getLocale();
}

function getByLocale($array)
{
    return Arr::get($array, locale(), $array['en']);
}
