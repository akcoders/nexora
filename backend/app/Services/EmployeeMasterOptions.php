<?php

namespace App\Services;

use App\Models\User;

class EmployeeMasterOptions
{
    /**
     * Languages an employee can mark as known, mapped to the Google Translate language code
     * used by the in-app language switcher.
     *
     * @var array<string, string>
     */
    private const LANGUAGES = [
        'English' => 'en',
        'Hindi' => 'hi',
        'Marathi' => 'mr',
        'Gujarati' => 'gu',
        'Tamil' => 'ta',
        'Telugu' => 'te',
        'Kannada' => 'kn',
        'Malayalam' => 'ml',
        'Bengali' => 'bn',
        'Punjabi' => 'pa',
        'Odia' => 'or',
        'Urdu' => 'ur',
        'Assamese' => 'as',
        'Nepali' => 'ne',
    ];

    /**
     * Statuses for police verification and background checks.
     *
     * @var array<string, array{label: string, tone: string}>
     */
    private const VERIFICATION_STATUSES = [
        'not_started' => ['label' => 'Not started', 'tone' => 'secondary'],
        'in_progress' => ['label' => 'In progress', 'tone' => 'warning'],
        'verified' => ['label' => 'Verified / Cleared', 'tone' => 'success'],
        'rejected' => ['label' => 'Rejected / Failed', 'tone' => 'danger'],
        'expired' => ['label' => 'Expired - renewal due', 'tone' => 'danger'],
        'not_required' => ['label' => 'Not required', 'tone' => 'info'],
    ];

    /** @return array<string, string> language name => Google Translate code */
    public static function languages(): array
    {
        return self::LANGUAGES;
    }

    /** @return list<string> */
    public static function languageNames(): array
    {
        return array_keys(self::LANGUAGES);
    }

    public static function languageCode(?string $language): string
    {
        return self::LANGUAGES[$language] ?? 'en';
    }

    public static function isLanguage(?string $language): bool
    {
        return $language !== null && array_key_exists($language, self::LANGUAGES);
    }

    /** @return array<string, array{label: string, tone: string}> */
    public static function verificationStatuses(): array
    {
        return self::VERIFICATION_STATUSES;
    }

    /** @return list<string> */
    public static function verificationStatusKeys(): array
    {
        return array_keys(self::VERIFICATION_STATUSES);
    }

    public static function verificationLabel(?string $status): string
    {
        return self::VERIFICATION_STATUSES[$status]['label'] ?? 'Not started';
    }

    public static function verificationTone(?string $status): string
    {
        return self::VERIFICATION_STATUSES[$status]['tone'] ?? 'secondary';
    }

    /**
     * Languages the signed-in user may switch the interface to. Employees see the
     * languages recorded on their master; everyone always has English.
     *
     * @return array<string, string> language name => Google Translate code
     */
    public static function switcherLanguagesFor(?User $user): array
    {
        $known = $user?->employeeProfile?->languages ?? [];
        $known = array_values(array_filter(is_array($known) ? $known : [], fn ($language) => self::isLanguage($language)));

        if ($user?->preferred_language && self::isLanguage($user->preferred_language)) {
            $known[] = $user->preferred_language;
        }

        if ($known === []) {
            return self::LANGUAGES;
        }

        $languages = ['English' => 'en'];
        foreach ($known as $language) {
            $languages[$language] = self::LANGUAGES[$language];
        }

        return $languages;
    }

    public static function preferredLanguageFor(?User $user): string
    {
        if ($user?->preferred_language && self::isLanguage($user->preferred_language)) {
            return $user->preferred_language;
        }

        $known = $user?->employeeProfile?->languages ?? [];

        foreach (is_array($known) ? $known : [] as $language) {
            if (self::isLanguage($language)) {
                return $language;
            }
        }

        return 'English';
    }
}
