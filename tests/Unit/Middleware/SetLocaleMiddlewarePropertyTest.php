<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SetLocaleMiddleware;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Property-Based Tests for SetLocaleMiddleware
 * 
 * Feature: phase-0-infrastructure, Property 2: Locale Middleware Handling
 * Validates: Requirements 4.1, 4.2, 4.3
 */
class SetLocaleMiddlewarePropertyTest extends TestCase
{
    use TestTrait;

    protected SetLocaleMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SetLocaleMiddleware();
    }

    /**
     * Property 2: Locale Middleware Handling - Supported Locales
     * 
     * For any HTTP request with a valid Accept-Language header containing
     * a supported locale (en, ar), the application locale SHALL be set to that value.
     * 
     * **Validates: Requirements 4.1, 4.2**
     */
    public function test_supported_locale_sets_application_locale(): void
    {
        $supportedLocales = ['en', 'ar'];

        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(...$supportedLocales)
            )
            ->then(function ($locale) {
                $request = Request::create('/api/test', 'GET');
                $request->headers->set('Accept-Language', $locale);

                $this->middleware->handle($request, function ($req) {
                    return response()->json(['status' => 'ok']);
                });

                $this->assertEquals($locale, App::getLocale());
            });
    }

    /**
     * Property 2: Locale Middleware Handling - Extended Locale Codes
     * 
     * For any HTTP request with Accept-Language header containing extended
     * locale codes (e.g., 'en-US', 'ar-SA'), the middleware SHALL extract
     * the primary language code and set the locale accordingly.
     * 
     * **Validates: Requirements 4.1, 4.2**
     */
    public function test_extended_locale_codes_extract_primary_language(): void
    {
        $extendedLocales = [
            'en-US' => 'en',
            'en-GB' => 'en',
            'ar-SA' => 'ar',
            'ar-EG' => 'ar',
        ];

        foreach ($extendedLocales as $extended => $expected) {
            $request = Request::create('/api/test', 'GET');
            $request->headers->set('Accept-Language', $extended);

            $this->middleware->handle($request, function ($req) {
                return response()->json(['status' => 'ok']);
            });

            $this->assertEquals($expected, App::getLocale());
        }
    }

    /**
     * Property 2: Locale Middleware Handling - Missing Header Defaults to English
     * 
     * For any request without the Accept-Language header,
     * the application locale SHALL default to 'en'.
     * 
     * **Validates: Requirements 4.3**
     */
    public function test_missing_header_defaults_to_english(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\constant(null)
            )
            ->then(function ($nullValue) {
                $request = Request::create('/api/test', 'GET');
                // No Accept-Language header set

                $this->middleware->handle($request, function ($req) {
                    return response()->json(['status' => 'ok']);
                });

                $this->assertEquals('en', App::getLocale());
            });
    }

    /**
     * Property 2: Locale Middleware Handling - Unsupported Locale Defaults to English
     * 
     * For any request with an unsupported locale in the Accept-Language header,
     * the application locale SHALL default to 'en'.
     * 
     * **Validates: Requirements 4.3**
     */
    public function test_unsupported_locale_defaults_to_english(): void
    {
        $unsupportedLocales = ['fr', 'de', 'es', 'zh', 'ja', 'ko', 'ru', 'pt', 'it', 'nl'];

        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(...$unsupportedLocales)
            )
            ->then(function ($locale) {
                $request = Request::create('/api/test', 'GET');
                $request->headers->set('Accept-Language', $locale);

                $this->middleware->handle($request, function ($req) {
                    return response()->json(['status' => 'ok']);
                });

                $this->assertEquals('en', App::getLocale());
            });
    }

    /**
     * Property 2: Locale Middleware Handling - Empty Header Defaults to English
     * 
     * For any request with an empty Accept-Language header,
     * the application locale SHALL default to 'en'.
     * 
     * **Validates: Requirements 4.3**
     */
    public function test_empty_header_defaults_to_english(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept-Language', '');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['status' => 'ok']);
        });

        $this->assertEquals('en', App::getLocale());
    }

    /**
     * Property 2: Locale Middleware Handling - Response Passthrough
     * 
     * For any request, the middleware SHALL pass the request to the next
     * handler and return its response unchanged.
     * 
     * **Validates: Requirements 4.1, 4.2, 4.3**
     */
    public function test_middleware_passes_request_and_returns_response(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements('en', 'ar', 'fr', 'de', '')
            )
            ->then(function ($locale) {
                $request = Request::create('/api/test', 'GET');
                if (!empty($locale)) {
                    $request->headers->set('Accept-Language', $locale);
                }

                $expectedData = ['test' => 'data', 'locale' => $locale];
                
                $response = $this->middleware->handle($request, function ($req) use ($expectedData) {
                    return response()->json($expectedData);
                });

                $content = json_decode($response->getContent(), true);
                $this->assertEquals($expectedData, $content);
            });
    }
}
