<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * Base API Controller - contains additional security schemes for the API
 *
 * @OA\SecurityScheme(
 *     securityScheme="session",
 *     type="apiKey",
 *     in="cookie",
 *     name="laravel_session",
 *     description="Laravel session authentication"
 * )
 */
class ApiController extends Controller
{
    //
}