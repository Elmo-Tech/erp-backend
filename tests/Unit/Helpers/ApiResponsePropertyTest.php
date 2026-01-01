<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ApiResponse;
use Eris\Generator;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property-Based Tests for ApiResponse Helper
 * 
 * Feature: phase-0-infrastructure, Property 1: ApiResponse Structure Consistency
 * Validates: Requirements 2.1, 2.2, 2.3
 */
class ApiResponsePropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 1: ApiResponse Structure Consistency - Success Response
     * 
     * For any data value and status code passed to ApiResponse::success(),
     * the returned JSON SHALL contain success: true, message, and data fields.
     * 
     * **Validates: Requirements 2.1, 2.2, 2.3**
     */
    public function test_success_response_always_contains_required_fields(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string(),
                Generator\choose(200, 299)
            )
            ->then(function ($message, $statusCode) {
                $testData = ['key' => 'value', 'number' => 123];
                
                $response = ApiResponse::success($testData, $message, $statusCode);
                $content = json_decode($response->getContent(), true);
                
                // Must contain success: true
                $this->assertArrayHasKey('success', $content);
                $this->assertTrue($content['success']);
                
                // Must contain message field
                $this->assertArrayHasKey('message', $content);
                $this->assertEquals($message, $content['message']);
                
                // Must contain data field
                $this->assertArrayHasKey('data', $content);
                $this->assertEquals($testData, $content['data']);
                
                // Status code must match
                $this->assertEquals($statusCode, $response->getStatusCode());
            });
    }

    /**
     * Property 1: ApiResponse Structure Consistency - Error Response
     * 
     * For any error message and status code passed to ApiResponse::error(),
     * the returned JSON SHALL contain success: false and message fields,
     * with optional errors array when provided.
     * 
     * **Validates: Requirements 2.1, 2.2, 2.3**
     */
    public function test_error_response_always_contains_required_fields(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string(),
                Generator\choose(400, 599)
            )
            ->then(function ($message, $statusCode) {
                $response = ApiResponse::error($message, $statusCode);
                $content = json_decode($response->getContent(), true);
                
                // Must contain success: false
                $this->assertArrayHasKey('success', $content);
                $this->assertFalse($content['success']);
                
                // Must contain message field
                $this->assertArrayHasKey('message', $content);
                $this->assertEquals($message, $content['message']);
                
                // Status code must match
                $this->assertEquals($statusCode, $response->getStatusCode());
            });
    }

    /**
     * Property 1: ApiResponse Structure Consistency - Error with Errors Array
     * 
     * When errors array is provided to ApiResponse::error(),
     * the response SHALL include the errors array.
     * 
     * **Validates: Requirements 2.2**
     */
    public function test_error_response_includes_errors_array_when_provided(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string(),
                Generator\choose(400, 599)
            )
            ->then(function ($message, $statusCode) {
                $errors = ['field1' => ['Error 1'], 'field2' => ['Error 2']];
                
                $response = ApiResponse::error($message, $statusCode, $errors);
                $content = json_decode($response->getContent(), true);
                
                // Must contain errors array
                $this->assertArrayHasKey('errors', $content);
                $this->assertEquals($errors, $content['errors']);
            });
    }

    /**
     * Property 1: ApiResponse Structure Consistency - Validation Error
     * 
     * ApiResponse::validationError() SHALL return status 422
     * with success: false and errors array.
     * 
     * **Validates: Requirements 2.2, 2.3**
     */
    public function test_validation_error_returns_422_with_errors(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string()
            )
            ->then(function ($message) {
                $errors = ['email' => ['Invalid email format']];
                
                $response = ApiResponse::validationError($errors, $message);
                $content = json_decode($response->getContent(), true);
                
                // Must be 422 status
                $this->assertEquals(422, $response->getStatusCode());
                
                // Must contain success: false
                $this->assertFalse($content['success']);
                
                // Must contain errors
                $this->assertArrayHasKey('errors', $content);
                $this->assertEquals($errors, $content['errors']);
            });
    }

    /**
     * Property 1: ApiResponse Structure Consistency - Not Found
     * 
     * ApiResponse::notFound() SHALL return status 404 with success: false.
     * 
     * **Validates: Requirements 2.2, 2.3**
     */
    public function test_not_found_returns_404(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string()
            )
            ->then(function ($message) {
                $response = ApiResponse::notFound($message);
                $content = json_decode($response->getContent(), true);
                
                $this->assertEquals(404, $response->getStatusCode());
                $this->assertFalse($content['success']);
                $this->assertEquals($message, $content['message']);
            });
    }

    /**
     * Property 1: ApiResponse Structure Consistency - Unauthorized
     * 
     * ApiResponse::unauthorized() SHALL return status 401 with success: false.
     * 
     * **Validates: Requirements 2.2, 2.3**
     */
    public function test_unauthorized_returns_401(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string()
            )
            ->then(function ($message) {
                $response = ApiResponse::unauthorized($message);
                $content = json_decode($response->getContent(), true);
                
                $this->assertEquals(401, $response->getStatusCode());
                $this->assertFalse($content['success']);
                $this->assertEquals($message, $content['message']);
            });
    }

    /**
     * Property 1: ApiResponse Structure Consistency - Forbidden
     * 
     * ApiResponse::forbidden() SHALL return status 403 with success: false.
     * 
     * **Validates: Requirements 2.2, 2.3**
     */
    public function test_forbidden_returns_403(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string()
            )
            ->then(function ($message) {
                $response = ApiResponse::forbidden($message);
                $content = json_decode($response->getContent(), true);
                
                $this->assertEquals(403, $response->getStatusCode());
                $this->assertFalse($content['success']);
                $this->assertEquals($message, $content['message']);
            });
    }
}
