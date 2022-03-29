<?php

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Essentially a specialized factory for Symfony's JsonResponse.
 */
final class ApiResponse
{
    public static function ok($data = null)
    {
        return self::genericResponse($data);
    }
    
    public static function notFound($data = null)
    {
        return self::genericError($data, JsonResponse::HTTP_NOT_FOUND);
    }
    
    public static function unauthorized($data = null)
    {
        return self::genericError($data, JsonResponse::HTTP_UNAUTHORIZED);
    }
    
    public static function inputDataError($data = null)
    {
        return self::genericError($data, JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
    
    public static function internalError($data = null)
    {
        return self::genericError($data, JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
    
    /**
     * Universal entry point for error responses.
     * Doesn't do anything meaningful right now.
     * @param mixed $data
     * @param int $status_code HTTP status code.
     * @return JsonResponse
     */
    private static function genericError($data = null, $status_code = JsonResponse::HTTP_INTERNAL_SERVER_ERROR)
    {
        return self::genericResponse($data, $status_code);
    }
    
    private static function genericResponse($data = null, $status_code = null)
    {
        return new JsonResponse($data, $status_code ?? JsonResponse::HTTP_OK);
    }
}
