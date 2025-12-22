<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private array $supportedLocales = ['en', 'fa', 'ar', 'es', 'fr', 'de'];
    private string $defaultLocale = 'en';

    public function handle(Request $request, \Closure $next): Response
    {
        $locale = $this->determineLocale($request);

        App::setLocale($locale);
        Session::put('locale', $locale);

        // Set RTL direction for RTL languages
        $rtlLanguages = ['fa', 'ar', 'he', 'ur'];
        $direction = in_array($locale, $rtlLanguages) ? 'rtl' : 'ltr';

        config(['app.direction' => $direction]);

        return $next($request);
    }

    private function determineLocale(Request $request): string
    {
        // 1. Check URL parameter
        if ($request->has('lang') && $this->isValidLocale($request->get('lang'))) {
            return $request->get('lang');
        }

        // 2. Check session
        if (Session::has('locale') && $this->isValidLocale(Session::get('locale'))) {
            return Session::get('locale');
        }

        // 3. Check user preference (if authenticated)
        if (auth()->check() && auth()->user()->locale) {
            $userLocale = auth()->user()->locale;
            if ($this->isValidLocale($userLocale)) {
                return $userLocale;
            }
        }

        // 4. Check Accept-Language header
        $headerLocale = $this->parseAcceptLanguage($request->header('Accept-Language'));
        if ($headerLocale && $this->isValidLocale($headerLocale)) {
            return $headerLocale;
        }

        // 5. Fallback to default
        return $this->defaultLocale;
    }

    private function isValidLocale(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales);
    }

    private function parseAcceptLanguage(?string $acceptLanguage): ?string
    {
        if (! $acceptLanguage) {
            return null;
        }

        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            $locale = trim(explode(';', $language)[0]);
            $locale = substr($locale, 0, 2); // Get language code only

            if ($this->isValidLocale($locale)) {
                return $locale;
            }
        }

        return null;
    }
}
