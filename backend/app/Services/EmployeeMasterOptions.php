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

    /**
     * Departments with the designations offered for each. The names include every
     * department and designation the seeders and earlier free-text entries used.
     *
     * @var array<string, list<string>>
     */
    private const DEPARTMENTS = [
        'Service' => ['HVAC Technician', 'Senior Technician', 'Helper', 'Service Engineer', 'Service Supervisor', 'Service Manager'],
        'Installation & Projects' => ['Installation Technician', 'Project Engineer', 'Site Supervisor', 'Project Manager'],
        'Sales' => ['Sales Executive', 'Sales Engineer', 'Sales Manager'],
        'Customer Support' => ['Customer Support Executive', 'Call Coordinator', 'Service Coordinator'],
        'Stores' => ['Storekeeper', 'Inventory Executive', 'Purchase Executive'],
        'Accounts' => ['Accountant', 'Accounts Executive', 'Finance Manager'],
        'HR' => ['HR Executive', 'HR Manager', 'Recruiter'],
        'Administration' => ['Administrator', 'Admin Executive', 'Office Assistant', 'Driver'],
        'Operations' => ['Operations Executive', 'Operations Manager', 'Branch Manager'],
        'Management' => ['Director', 'General Manager', 'Chief Executive Officer'],
    ];

    /**
     * Fixed dropdown lists used by the employee onboarding form. Values are stored as shown,
     * except where a list maps a stored key to a label.
     *
     * @var array<string, array<int|string, string>>
     */
    private const LISTS = [
        'salutations' => ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'],
        'genders' => ['male' => 'Male', 'female' => 'Female', 'transgender' => 'Transgender', 'other' => 'Other'],
        'marital_statuses' => ['unmarried' => 'Unmarried', 'married' => 'Married', 'other' => 'Other'],
        'blood_groups' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'nationalities' => ['Indian', 'Nepalese', 'Bhutanese', 'Bangladeshi', 'Sri Lankan', 'Other'],
        'religions' => ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist', 'Jain', 'Parsi', 'Jewish', 'Other', 'Prefer not to say'],
        'relations' => ['Spouse', 'Father', 'Mother', 'Brother', 'Sister', 'Son', 'Daughter', 'Friend', 'Other'],
        'employment_types' => ['permanent' => 'Permanent', 'probation' => 'Probation', 'contract' => 'Contract', 'intern' => 'Intern', 'consultant' => 'Consultant'],
        'employee_categories' => ['Field staff', 'Office staff', 'Supervisory', 'Management', 'Contract workforce', 'Apprentice / trainee'],
        'job_grades' => ['G1' => 'G1 · Helper / trainee', 'G2' => 'G2 · Technician / executive', 'G3' => 'G3 · Senior technician', 'G4' => 'G4 · Supervisor / engineer', 'G5' => 'G5 · Manager', 'G6' => 'G6 · Senior manager', 'G7' => 'G7 · Leadership'],
        'user_statuses' => ['active' => 'Active', 'inactive' => 'Inactive', 'on_leave' => 'On leave', 'suspended' => 'Suspended'],
        'currencies' => ['INR' => 'INR · Indian rupee', 'USD' => 'USD · US dollar', 'AED' => 'AED · UAE dirham', 'EUR' => 'EUR · Euro', 'GBP' => 'GBP · British pound'],
        'pay_frequencies' => ['monthly' => 'Monthly', 'weekly' => 'Weekly', 'daily' => 'Daily'],
        'pay_groups' => ['Staff', 'Field workforce', 'Contract labour', 'Management', 'Interns & trainees'],
        'yes_no' => ['yes' => 'Yes', 'no' => 'No'],
        'qualifications' => ['Below 10th', '10th (SSC)', '12th (HSC)', 'ITI', 'Diploma', 'Graduate', 'Engineering graduate', 'Post graduate', 'Doctorate'],
        'ppe_sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'],
        'safety_training' => ['Completed', 'Scheduled', 'Refresher due', 'Not completed', 'Not required'],
        'medical_fitness' => ['Fit', 'Fit with restrictions', 'Temporarily unfit', 'Check-up pending'],
        'hiring_sources' => ['social_media' => 'Social media', 'website' => 'Company website', 'agent' => 'Agent / consultancy', 'job_portal' => 'Job portal', 'direct' => 'Direct / walk-in', 'internal_staff' => 'Reference from internal staff'],
        'issue_statuses' => ['Issued', 'Not issued', 'Returned'],
        'access_statuses' => ['Granted', 'Requested', 'Revoked', 'Not required'],
    ];

    /** @return array<string, list<string>> department => designations */
    public static function departments(): array
    {
        return self::DEPARTMENTS;
    }

    /** @return list<string> */
    public static function designations(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::DEPARTMENTS))));
    }

    /**
     * Options of a fixed list as value => label.
     *
     * @return array<string, string>
     */
    public static function options(string $list): array
    {
        $options = self::LISTS[$list] ?? throw new \InvalidArgumentException("Unknown employee option list [{$list}].");

        return array_is_list($options) ? array_combine($options, $options) : $options;
    }

    /**
     * Values accepted for a list. A value already saved on the record stays valid even when
     * it is no longer offered, so older free-text entries do not block saving the form.
     *
     * @param  array<int|string, string>|string  $list  list name or explicit values
     * @return list<string>
     */
    public static function allowed(array|string $list, ?string $current = null): array
    {
        $values = is_string($list) ? array_map('strval', array_keys(self::options($list))) : array_values($list);

        return filled($current) && ! in_array($current, $values, true) ? [...$values, $current] : $values;
    }

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
