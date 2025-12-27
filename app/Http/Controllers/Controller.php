<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Business OS API",
 *     description="ERP/CRM System API for Software Development Agencies. This API provides endpoints for managing sales, projects, tasks, time tracking, and client portal functionality.",
 *     @OA\Contact(
 *         email="support@business-os.com",
 *         name="Business OS Support"
 *     ),
 *     @OA\License(
 *         name="Proprietary",
 *         url="https://business-os.com/license"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token to access protected endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Health",
 *     description="API Health check endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for user authentication"
 * )
 *
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints for user management"
 * )
 *
 * @OA\Tag(
 *     name="Sales",
 *     description="API Endpoints for leads and quotations"
 * )
 *
 * @OA\Tag(
 *     name="Projects",
 *     description="API Endpoints for project management"
 * )
 *
 * @OA\Tag(
 *     name="Tasks",
 *     description="API Endpoints for task management"
 * )
 *
 * @OA\Tag(
 *     name="Time Tracking",
 *     description="API Endpoints for time entries and attendance"
 * )
 *
 * @OA\Schema(
 *     schema="ApiSuccessResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operation successful"),
 *     @OA\Property(property="data", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="ApiErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="An error occurred"),
 *     @OA\Property(property="errors", type="array", @OA\Items(type="string"))
 * )
 *
 * @OA\Schema(
 *     schema="ValidationErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Validation failed"),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(type="string")
 *         )
 *     )
 * )
 */
abstract class Controller
{
    //
}
