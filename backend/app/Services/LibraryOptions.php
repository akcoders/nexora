<?php

namespace App\Services;

use App\Models\ServiceMasterOption;

/**
 * Lists used by the knowledge library: resource kinds, the admin-managed device type and
 * manufacturer masters, upload limits and YouTube link parsing.
 */
class LibraryOptions
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /** @var array<string, array{label: string, tone: string}> */
    public const STATUSES = [
        self::STATUS_PUBLISHED => ['label' => 'Published', 'tone' => 'success'],
        self::STATUS_DRAFT => ['label' => 'Draft', 'tone' => 'secondary'],
    ];

    /** @var array<string, array{label: string, plural: string, icon: string, tone: string}> */
    public const KINDS = [
        'video' => ['label' => 'YouTube video', 'plural' => 'Videos', 'icon' => 'square-play', 'tone' => 'danger'],
        'document' => ['label' => 'Document', 'plural' => 'Documents', 'icon' => 'file-text', 'tone' => 'primary'],
        'link' => ['label' => 'Web link', 'plural' => 'Links', 'icon' => 'link', 'tone' => 'info'],
    ];

    /**
     * Content filter for browsing: guides with written steps, or with a resource of a kind.
     *
     * @var array<string, array{label: string, icon: string}>
     */
    public const CONTENT_FILTERS = [
        'article' => ['label' => 'Written guide', 'icon' => 'notebook-text'],
        'video' => ['label' => 'Video', 'icon' => 'square-play'],
        'document' => ['label' => 'Document', 'icon' => 'file-text'],
        'link' => ['label' => 'Web link', 'icon' => 'link'],
    ];

    /** @var array<string, string> tone keys the category colour picker offers */
    public const TONES = ['primary' => 'Blue', 'info' => 'Sky', 'success' => 'Green', 'warning' => 'Amber', 'danger' => 'Red', 'purple' => 'Purple', 'teal' => 'Teal', 'secondary' => 'Slate'];

    /** @var list<array{name: string, icon: string, color: string, description: string}> */
    public const DEFAULT_CATEGORIES = [
        ['name' => 'Installation', 'icon' => 'hammer', 'color' => 'primary', 'description' => 'Site survey, mounting, piping, wiring and commissioning steps.'],
        ['name' => 'Preventive maintenance', 'icon' => 'shield-check', 'color' => 'success', 'description' => 'PM and AMC checklists, cleaning and periodic service.'],
        ['name' => 'Troubleshooting & error codes', 'icon' => 'stethoscope', 'color' => 'danger', 'description' => 'Fault finding, error code meanings and quick fixes.'],
        ['name' => 'Repair procedures', 'icon' => 'wrench', 'color' => 'warning', 'description' => 'Part replacement, gas charging and repair methods.'],
        ['name' => 'Safety', 'icon' => 'hard-hat', 'color' => 'purple', 'description' => 'Electrical, height and refrigerant handling safety.'],
        ['name' => 'Tools & instruments', 'icon' => 'gauge', 'color' => 'teal', 'description' => 'Using gauges, meters, vacuum pumps and other tools.'],
        ['name' => 'Product manuals', 'icon' => 'book-open', 'color' => 'info', 'description' => 'Service and installation manuals from manufacturers.'],
        ['name' => 'Training', 'icon' => 'graduation-cap', 'color' => 'secondary', 'description' => 'Learning material for new technicians.'],
    ];

    /** @var list<string> */
    public const DEFAULT_DEVICE_TYPES = ['Split AC', 'Window AC', 'Cassette AC', 'Ductable AC', 'Tower AC', 'VRF / VRV', 'Chiller', 'AHU', 'TFA', 'FCU', 'Cooling tower', 'Pump', 'Exhaust fan', 'Electrical panel', 'Controller / thermostat', 'Other'];

    /** @var list<string> */
    public const DEFAULT_MANUFACTURERS = ['Daikin', 'Voltas', 'Blue Star', 'Carrier', 'Mitsubishi Electric', 'Mitsubishi Heavy', 'LG', 'Samsung', 'Hitachi', 'O General', 'Panasonic', 'Toshiba', 'Lloyd', 'Godrej', 'Haier', 'Trane', 'York', 'Other'];

    /** Upload limit for library documents, in kilobytes. */
    public const MAX_DOCUMENT_KB = 20480;

    /** Upload limit for images placed inside the editor, in kilobytes. */
    public const MAX_IMAGE_KB = 5120;

    /** @var list<string> */
    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'webp'];

    /**
     * Admin-managed lists stored in service_master_options.
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public static function masterGroups(): array
    {
        return [
            'library_device_type' => ['label' => 'Device types', 'hint' => 'Equipment a guide applies to'],
            'library_manufacturer' => ['label' => 'Manufacturers', 'hint' => 'Brand / OEM a guide applies to'],
        ];
    }

    /** @return array<string, string> code => label of active device types */
    public static function deviceTypes(): array
    {
        return self::master('library_device_type');
    }

    /** @return array<string, string> code => label of active manufacturers */
    public static function manufacturers(): array
    {
        return self::master('library_manufacturer');
    }

    /**
     * Label for a stored code, falling back to inactive entries and then the code itself.
     */
    public static function label(string $type, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::master($type)[$code] ?? self::allLabels($type)[$code] ?? str($code)->headline()->toString();
    }

    /**
     * Labels of every entry of a type, inactive ones included, loaded once per request.
     *
     * @return array<string, string>
     */
    private static function allLabels(string $type): array
    {
        return once(fn (): array => ServiceMasterOption::query()->where('type', $type)->pluck('label', 'code')->all());
    }

    /** @return array<string, string> */
    public static function master(string $type): array
    {
        return once(fn (): array => ServiceMasterOption::query()
            ->where('type', $type)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label', 'code')
            ->all());
    }

    /**
     * The 11-character video id from any common YouTube link (watch, youtu.be, shorts, embed,
     * live, mobile and no-cookie hosts), or null when the link is not a YouTube video.
     */
    public static function youtubeId(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        $host = strtolower(preg_replace('/^(www\.|m\.|music\.)/i', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        $id = match (true) {
            $host === 'youtu.be' => explode('/', trim($path, '/'))[0] ?? null,
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true) && $path === '/watch' => $query['v'] ?? null,
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true) && preg_match('#^/(shorts|embed|live|v)/([^/?]+)#', $path, $match) === 1 => $match[2],
            default => null,
        };

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
    }

    public static function youtubeThumbnail(string $videoId): string
    {
        return "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg";
    }

    public static function youtubeEmbedUrl(string $videoId): string
    {
        return "https://www.youtube-nocookie.com/embed/{$videoId}?rel=0";
    }

    public static function youtubeWatchUrl(string $videoId): string
    {
        return "https://www.youtube.com/watch?v={$videoId}";
    }

    /** Human readable file size, e.g. 2.4 MB. */
    public static function fileSize(?int $bytes): ?string
    {
        if (! $bytes) {
            return null;
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), $power > 1 ? 1 : 0).' '.$units[$power];
    }
}
