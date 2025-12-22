<?php

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class LocalizationService
{
    private array $supportedLocales = [
        'en' => ['name' => 'English', 'native' => 'English', 'direction' => 'ltr'],
        'fa' => ['name' => 'Persian', 'native' => 'فارسی', 'direction' => 'rtl'],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'direction' => 'rtl'],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'direction' => 'ltr'],
        'fr' => ['name' => 'French', 'native' => 'Français', 'direction' => 'ltr'],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'direction' => 'ltr'],
    ];

    private array $rtlLocales = ['fa', 'ar', 'he', 'ur'];
    private array $dateFormats = [
        'en' => 'M j, Y',
        'fa' => 'j F Y',
        'ar' => 'j F Y',
        'es' => 'j \d\e F \d\e Y',
        'fr' => 'j F Y',
        'de' => 'j. F Y',
    ];

    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    public function isSupported(string $locale): bool
    {
        return array_key_exists($locale, $this->supportedLocales);
    }

    public function isRtl(string $locale = null): bool
    {
        $locale = $locale ?? App::getLocale();

        return in_array($locale, $this->rtlLocales);
    }

    public function getDirection(string $locale = null): string
    {
        $locale = $locale ?? App::getLocale();

        return $this->supportedLocales[$locale]['direction'] ?? 'ltr';
    }

    public function getLocaleName(string $locale, bool $native = false): string
    {
        if (! $this->isSupported($locale)) {
            return $locale;
        }

        return $native
            ? $this->supportedLocales[$locale]['native']
            : $this->supportedLocales[$locale]['name'];
    }

    public function formatDate(\DateTime $date, string $locale = null): string
    {
        $locale = $locale ?? App::getLocale();
        $format = $this->dateFormats[$locale] ?? $this->dateFormats['en'];

        // For Persian and Arabic, use Persian/Arabic numerals
        if (in_array($locale, ['fa', 'ar'])) {
            return $this->convertToLocalNumerals($date->format($format), $locale);
        }

        return $date->format($format);
    }

    public function formatNumber(int|float $number, string $locale = null): string
    {
        $locale = $locale ?? App::getLocale();

        switch ($locale) {
            case 'fa':
                return $this->convertToPersianNumerals((string) $number);
            case 'ar':
                return $this->convertToArabicNumerals((string) $number);
            default:
                return number_format($number);
        }
    }

    public function getTimeAgo(\DateTime $date, string $locale = null): string
    {
        $locale = $locale ?? App::getLocale();
        $now = new \DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return __('messages.years_ago', ['count' => $diff->y], $locale);
        } elseif ($diff->m > 0) {
            return __('messages.months_ago', ['count' => $diff->m], $locale);
        } elseif ($diff->d > 0) {
            return __('messages.days_ago', ['count' => $diff->d], $locale);
        } elseif ($diff->h > 0) {
            return __('messages.hours_ago', ['count' => $diff->h], $locale);
        } elseif ($diff->i > 0) {
            return __('messages.minutes_ago', ['count' => $diff->i], $locale);
        } else {
            return __('messages.now', [], $locale);
        }
    }

    public function translateContent(array $content, string $targetLocale): array
    {
        $cacheKey = "translated_content:" . md5(json_encode($content)) . ":{$targetLocale}";

        return Cache::remember($cacheKey, 3600, function () use ($content, $targetLocale) {
            // In a real implementation, this would call a translation service
            // For now, return the original content
            return $content;
        });
    }

    public function getLocaleFromAcceptLanguage(string $acceptLanguage): ?string
    {
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            $locale = trim(explode(';', $language)[0]);
            $locale = substr($locale, 0, 2);

            if ($this->isSupported($locale)) {
                return $locale;
            }
        }

        return null;
    }

    public function getCurrencySymbol(string $locale = null): string
    {
        $locale = $locale ?? App::getLocale();

        return match($locale) {
            'fa' => 'ریال',
            'ar' => 'ر.س',
            'es' => '€',
            'fr' => '€',
            'de' => '€',
            default => '$'
        };
    }

    public function getCalendarType(string $locale = null): string
    {
        $locale = $locale ?? App::getLocale();

        return match($locale) {
            'fa' => 'persian',
            'ar' => 'hijri',
            default => 'gregorian'
        };
    }

    public function exportTranslations(string $locale): array
    {
        $translations = [];
        $langPath = lang_path($locale);

        if (! File::exists($langPath)) {
            return [];
        }

        $files = File::files($langPath);

        foreach ($files as $file) {
            $key = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $translations[$key] = include $file->getPathname();
        }

        return $translations;
    }

    public function importTranslations(string $locale, array $translations): bool
    {
        try {
            $langPath = lang_path($locale);

            if (! File::exists($langPath)) {
                File::makeDirectory($langPath, 0755, true);
            }

            foreach ($translations as $file => $content) {
                $filePath = $langPath . '/' . $file . '.php';
                $phpContent = "<?php\n\nreturn " . var_export($content, true) . ";\n";
                File::put($filePath, $phpContent);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function validateTranslations(string $locale): array
    {
        $issues = [];
        $baseTranslations = $this->exportTranslations('en');
        $localeTranslations = $this->exportTranslations($locale);

        foreach ($baseTranslations as $file => $translations) {
            if (! isset($localeTranslations[$file])) {
                $issues[] = "Missing translation file: {$file}";

                continue;
            }

            $missing = array_diff_key($translations, $localeTranslations[$file]);
            foreach ($missing as $key => $value) {
                $issues[] = "Missing translation key: {$file}.{$key}";
            }
        }

        return $issues;
    }

    private function convertToPersianNumerals(string $text): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($english, $persian, $text);
    }

    private function convertToArabicNumerals(string $text): string
    {
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($english, $arabic, $text);
    }

    private function convertToLocalNumerals(string $text, string $locale): string
    {
        return match($locale) {
            'fa' => $this->convertToPersianNumerals($text),
            'ar' => $this->convertToArabicNumerals($text),
            default => $text
        };
    }

    public function getLocaleConfig(string $locale = null): array
    {
        $locale = $locale ?? App::getLocale();

        return [
            'locale' => $locale,
            'name' => $this->getLocaleName($locale),
            'native_name' => $this->getLocaleName($locale, true),
            'direction' => $this->getDirection($locale),
            'is_rtl' => $this->isRtl($locale),
            'currency_symbol' => $this->getCurrencySymbol($locale),
            'calendar_type' => $this->getCalendarType($locale),
            'date_format' => $this->dateFormats[$locale] ?? $this->dateFormats['en'],
        ];
    }
}
