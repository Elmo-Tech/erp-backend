<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to set application locale based on Accept-Language header.
 * 
 * Supports: en (English), ar (Arabic)
 * Default: en (English)
 */
class SetLocaleMiddleware
{
    /**
     * Supported locales for the application.
     *
     * @var array<string>
     */
    protected array $supportedLocales = ['en', 'ar'];

    /**
     * Default locale when none is provided or unsupported.
     *
     * @var string
     */
    protected string $defaultLocale = 'en';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->parseLocale($request->header('Accept-Language'));
        
        App::setLocale($locale);

        return $next($request);
    }

    /**
     * Parse the Accept-Language header and return a supported locale.
     *
     * @param  string|null  $acceptLanguage
     * @return string
     */
    protected function parseLocale(?string $acceptLanguage): string
    {
        if (empty($acceptLanguage)) {
            return $this->defaultLocale;
        }

        // Extract primary language code (e.g., 'en-US' -> 'en', 'ar-SA' -> 'ar')
        $locale = substr($acceptLanguage, 0, 2);

        if (in_array($locale, $this->supportedLocales, true)) {
            return $locale;
        }

        return $this->defaultLocale;
    }
}
