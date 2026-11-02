<?php

namespace App\Services;

use App\Models\ServiceMasterOption;
use Illuminate\Support\Carbon;

/**
 * Fixed dropdown lists, SLA defaults, pending-age buckets and the equipment-wise
 * technician diagnosis fields used by the complaint register.
 */
class ComplaintOptions
{
    /** @var array<string, array{label: string, tone: string, icon: string}> */
    public const STATUSES = [
        'open' => ['label' => 'Open', 'tone' => 'primary', 'icon' => 'inbox'],
        'assigned' => ['label' => 'Assigned', 'tone' => 'info', 'icon' => 'user-check'],
        'scheduled' => ['label' => 'Scheduled', 'tone' => 'info', 'icon' => 'calendar-clock'],
        'in_progress' => ['label' => 'In progress', 'tone' => 'warning', 'icon' => 'loader'],
        'pending' => ['label' => 'Pending', 'tone' => 'danger', 'icon' => 'hourglass'],
        'under_observation' => ['label' => 'Under observation', 'tone' => 'purple', 'icon' => 'eye'],
        'completed' => ['label' => 'Work completed', 'tone' => 'success', 'icon' => 'circle-check'],
        'closed' => ['label' => 'Closed', 'tone' => 'secondary', 'icon' => 'lock'],
        'cancelled' => ['label' => 'Cancelled', 'tone' => 'dark', 'icon' => 'ban'],
    ];

    /** Statuses that still need work from the service team. */
    public const ACTIVE_STATUSES = ['open', 'assigned', 'scheduled', 'in_progress', 'pending', 'under_observation'];

    /** @var array<string, array{label: string, tone: string}> */
    public const VISIT_STATUSES = [
        'scheduled' => ['label' => 'Scheduled', 'tone' => 'info'],
        'accepted' => ['label' => 'Accepted', 'tone' => 'primary'],
        'en_route' => ['label' => 'En route', 'tone' => 'warning'],
        'on_site' => ['label' => 'On site', 'tone' => 'warning'],
        'completed' => ['label' => 'Completed', 'tone' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'tone' => 'dark'],
    ];

    /** @var array<string, array{label: string, tone: string, status: string}> */
    public const VISIT_OUTCOMES = [
        'resolved' => ['label' => 'Resolved - unit working OK', 'tone' => 'success', 'status' => 'completed'],
        'pending' => ['label' => 'Pending - more work needed', 'tone' => 'danger', 'status' => 'pending'],
        'observation' => ['label' => 'Under observation', 'tone' => 'purple', 'status' => 'under_observation'],
    ];

    /** @var array<string, array{label: string, response: int, resolution: int, tone: string}> SLA hours per priority */
    public const PRIORITIES = [
        'critical' => ['label' => 'Critical', 'response' => 2, 'resolution' => 24, 'tone' => 'danger'],
        'high' => ['label' => 'High', 'response' => 4, 'resolution' => 48, 'tone' => 'warning'],
        'medium' => ['label' => 'Medium', 'response' => 8, 'resolution' => 72, 'tone' => 'info'],
        'low' => ['label' => 'Low', 'response' => 24, 'resolution' => 120, 'tone' => 'secondary'],
    ];

    /** @var array<string, array{label: string, icon: string, description: string}> */
    public const CALL_TYPES = [
        'breakdown' => ['label' => 'Breakdown call', 'icon' => 'siren', 'description' => 'Unit stopped or faulty'],
        'preventive' => ['label' => 'Preventive service', 'icon' => 'shield-check', 'description' => 'Scheduled PM / AMC service'],
        'installation' => ['label' => 'Installation', 'icon' => 'hammer', 'description' => 'New unit installation or shifting'],
        'site_visit' => ['label' => 'Site visit', 'icon' => 'map-pinned', 'description' => 'Survey, inspection or audit'],
    ];

    /** @var array<string, array<string, string>> */
    private const LISTS = [
        'call_modes' => ['phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'portal' => 'Customer portal', 'amc_visit' => 'During AMC visit', 'other' => 'Other'],
        'sources' => ['customer' => 'Customer', 'architect' => 'Architect / consultant', 'technician' => 'Technician', 'amc_pm' => 'AMC / PM visit', 'internal' => 'Internal', 'project' => 'Project team'],
        'equipment_types' => ['split' => 'Split AC', 'cassette' => 'Cassette AC', 'vrf' => 'VRF / VRV', 'ahu' => 'AHU', 'chiller' => 'Chiller', 'tfa' => 'TFA', 'exhaust_fan' => 'Exhaust fan', 'pump' => 'Pump', 'electrical' => 'Electrical panel', 'cooling_tower' => 'Cooling tower', 'other' => 'Other'],
        'criticality' => ['critical' => 'Critical', 'normal' => 'Normal'],
        'refrigerants' => ['R22' => 'R22', 'R407C' => 'R407C', 'R32' => 'R32', 'R410A' => 'R410A', 'R134a' => 'R134a', 'other' => 'Other'],
        'compressor_types' => ['rotary' => 'Rotary', 'reciprocating' => 'Reciprocating', 'scroll' => 'Scroll', 'screw' => 'Screw', 'centrifugal' => 'Centrifugal'],
        'technologies' => ['inverter' => 'Inverter', 'non_inverter' => 'Non-inverter', 'vrf' => 'VRF'],
        'power_supplies' => ['single_phase' => 'Single phase', 'three_phase' => '3 phase'],
        'service_categories' => ['warranty' => 'Warranty', 'amc' => 'AMC', 'gsc' => 'GSC', 'paid' => 'Paid service', 'company_amc' => 'Company AMC'],
        'assignment_methods' => ['app' => 'Mobile app', 'whatsapp' => 'WhatsApp', 'phone' => 'Phone call', 'in_person' => 'In person', 'email' => 'Email'],
        'conditions' => ['good' => 'Good', 'average' => 'Average', 'poor' => 'Poor', 'faulty' => 'Faulty', 'na' => 'Not applicable'],
        'final_results' => ['working' => 'Unit working OK', 'not_working' => 'Not working'],
        'pending_groups' => ['internal' => 'Pending internal', 'customer' => 'Pending customer', 'materials' => 'Pending materials', 'technical' => 'Pending technical', 'site' => 'Pending site', 'other' => 'Other'],
        'pending_owners' => ['service_engineer' => 'Service engineer', 'service_manager' => 'Service manager', 'purchase' => 'Purchase', 'store' => 'Store', 'accounts' => 'Accounts', 'sales' => 'Sales', 'customer' => 'Customer', 'vendor' => 'Vendor', 'oem' => 'OEM', 'project_team' => 'Project team'],
        'delay_reasons' => ['internal' => 'Internal', 'external' => 'External', 'purchase' => 'Purchase', 'vendor' => 'Vendor', 'customer' => 'Customer', 'technician_availability' => 'Technician availability', 'material_shortage' => 'Material shortage', 'approval_delay' => 'Approval delay', 'site_issues' => 'Site issues', 'technical_complexity' => 'Technical complexity', 'other' => 'Other'],
        'part_types' => ['warranty' => 'Warranty', 'oem_amc' => 'OEM AMC', 'internal_amc' => 'Internal AMC', 'chargeable' => 'Chargeable'],
        'part_sources' => ['stock' => 'Company stock', 'purchase' => 'Local purchase', 'oem' => 'OEM supplied', 'customer' => 'Customer supplied', 'vendor' => 'Vendor'],
        'uoms' => ['nos' => 'Nos', 'set' => 'Set', 'kg' => 'Kg', 'mtr' => 'Metre', 'ltr' => 'Litre', 'ft' => 'Feet', 'roll' => 'Roll', 'pair' => 'Pair'],
        'approval_statuses' => ['not_required' => 'Not required', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'],
        'purchase_statuses' => ['not_required' => 'Not required', 'requested' => 'Requested', 'po_raised' => 'PO raised', 'in_transit' => 'In transit', 'received' => 'Received', 'issued' => 'Issued to technician'],
        'payment_statuses' => ['not_applicable' => 'Not applicable', 'pending' => 'Pending', 'partial' => 'Partially paid', 'paid' => 'Paid'],
        'yes_no' => ['yes' => 'Yes', 'no' => 'No'],
    ];

    /**
     * Pending reasons per group, from the complaint process sheet. Admins can add more in
     * Complaint masters; these are the starting set.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULT_PENDING_REASONS = [
        'internal' => ['Technician report pending', 'Service manager decision pending', 'Quotation not sent to customer', 'Purchase approval pending', 'Purchase pending'],
        'customer' => ['Customer approval pending', 'Customer PO pending', 'Customer payment pending', 'Site access pending', 'Customer unavailable'],
        'materials' => ['Spare parts required', 'Spare unavailable', 'Delivery pending', 'Special materials required'],
        'technical' => ['Senior engineer required', 'OEM support required', 'Further diagnosis required', 'Sub contractor required'],
        'site' => ['Civil work required', 'Electrical work required', 'Shutdown required', 'Equipment inaccessible', 'Other works to complete first'],
    ];

    /** @var array<string, list<string>> sub categories keyed by pending reason label */
    public const DEFAULT_PENDING_SUB_REASONS = [
        'Spare parts required' => ['Gas leakage', 'Compressor replacement', 'Inverter PCB replacement', 'Fan motor replacement', 'Capacitor replacement', 'Sensor replacement'],
        'Spare unavailable' => ['Not available with OEM', 'Not available locally', 'Obsolete model'],
    ];

    /** @var list<string> */
    public const DEFAULT_NATURES = ['Not cooling', 'Low cooling', 'Water leakage', 'Abnormal noise', 'Unit not starting', 'Error code on display', 'Tripping / power issue', 'Ice formation', 'Bad smell', 'Low airflow', 'Remote / controller issue', 'Preventive maintenance', 'Installation request', 'Other'];

    /** @var list<string> */
    public const DEFAULT_ROOT_CAUSES = ['Power supply issue', 'Improper installation', 'Mechanical damage', 'Theft', 'Wear & tear', 'Lack of maintenance', 'User operation error', 'Refrigerant leakage', 'Compressor failure', 'Expansion valve defective', 'PCB failure', 'Fan motor failure', 'Sensor failure', 'Capacitor failure', 'Other'];

    /** @var list<string> */
    public const DEFAULT_FAULT_CATEGORIES = ['Electrical', 'Mechanical', 'Refrigerant circuit', 'Electronic / PCB', 'Airflow', 'Water / drain', 'Installation', 'Controls', 'No fault found'];

    /**
     * Pending-age bands. Upper bound is inclusive; null means no upper bound.
     *
     * @var list<array{key: string, label: string, max: ?int, tone: string}>
     */
    public const AGE_BUCKETS = [
        ['key' => 'normal', 'label' => 'Normal', 'min' => 0, 'max' => 2, 'tone' => 'success'],
        ['key' => 'attention', 'label' => 'Attention', 'min' => 3, 'max' => 4, 'tone' => 'info'],
        ['key' => 'escalation', 'label' => 'Escalation', 'min' => 5, 'max' => 6, 'tone' => 'warning'],
        ['key' => 'serious', 'label' => 'Serious', 'min' => 7, 'max' => 9, 'tone' => 'orange'],
        ['key' => 'management', 'label' => 'Management attention', 'min' => 10, 'max' => 15, 'tone' => 'danger'],
        ['key' => 'critical', 'label' => 'Critical', 'min' => 16, 'max' => null, 'tone' => 'dark'],
    ];

    /**
     * Every reading or condition a technician can record. `unit` is shown next to number inputs.
     *
     * @var array<string, array{label: string, type: string, unit?: string}>
     */
    public const DIAGNOSIS_FIELDS = [
        'equipment_condition' => ['label' => 'Equipment condition', 'type' => 'condition'],
        'suction_pressure' => ['label' => 'Suction pressure', 'type' => 'number', 'unit' => 'psi'],
        'discharge_pressure' => ['label' => 'Discharge pressure', 'type' => 'number', 'unit' => 'psi'],
        'voltage' => ['label' => 'Voltage', 'type' => 'number', 'unit' => 'V'],
        'current' => ['label' => 'Current', 'type' => 'number', 'unit' => 'A'],
        'ambient_temp' => ['label' => 'Ambient temperature', 'type' => 'number', 'unit' => '°C'],
        'idu_supply_temp' => ['label' => 'IDU supply air temp', 'type' => 'number', 'unit' => '°C'],
        'idu_return_temp' => ['label' => 'IDU return air temp', 'type' => 'number', 'unit' => '°C'],
        'odu_supply_temp' => ['label' => 'ODU supply air temp', 'type' => 'number', 'unit' => '°C'],
        'odu_return_temp' => ['label' => 'ODU return air temp', 'type' => 'number', 'unit' => '°C'],
        'supply_air_temp' => ['label' => 'Supply air temp', 'type' => 'number', 'unit' => '°C'],
        'return_air_temp' => ['label' => 'Return air temp', 'type' => 'number', 'unit' => '°C'],
        'water_in_temp' => ['label' => 'Water inlet temp', 'type' => 'number', 'unit' => '°C'],
        'water_out_temp' => ['label' => 'Water outlet temp', 'type' => 'number', 'unit' => '°C'],
        'delta_t' => ['label' => 'Delta T', 'type' => 'number', 'unit' => '°C'],
        'error_code' => ['label' => 'Error code', 'type' => 'text'],
        'pcb_condition' => ['label' => 'PCB condition', 'type' => 'condition'],
        'compressor_condition' => ['label' => 'Compressor condition', 'type' => 'condition'],
        'idu_fan_motor' => ['label' => 'Fan motor condition (IDU)', 'type' => 'condition'],
        'odu_fan_motor' => ['label' => 'Fan motor condition (ODU)', 'type' => 'condition'],
        'fan_motor' => ['label' => 'Fan / motor condition', 'type' => 'condition'],
        'evaporator_condition' => ['label' => 'Evaporator condition', 'type' => 'condition'],
        'condenser_condition' => ['label' => 'Condenser condition', 'type' => 'condition'],
        'refrigerant_leakage' => ['label' => 'Refrigerant leakage', 'type' => 'yes_no'],
        'insulation_condition' => ['label' => 'Insulation condition', 'type' => 'condition'],
        'filter_condition' => ['label' => 'Filter condition', 'type' => 'condition'],
        'drain_condition' => ['label' => 'Drain condition', 'type' => 'condition'],
        'electrical_connections' => ['label' => 'Electrical connections', 'type' => 'condition'],
    ];

    /** @var list<string> */
    private const DX_FIELDS = ['equipment_condition', 'suction_pressure', 'discharge_pressure', 'voltage', 'current', 'ambient_temp', 'idu_supply_temp', 'idu_return_temp', 'odu_supply_temp', 'odu_return_temp', 'delta_t', 'error_code', 'pcb_condition', 'compressor_condition', 'idu_fan_motor', 'odu_fan_motor', 'evaporator_condition', 'condenser_condition', 'refrigerant_leakage', 'insulation_condition', 'filter_condition', 'drain_condition', 'electrical_connections'];

    /** @var array<string, list<string>> equipment type => diagnosis field keys */
    private const EQUIPMENT_FIELDS = [
        'split' => self::DX_FIELDS,
        'cassette' => self::DX_FIELDS,
        'vrf' => self::DX_FIELDS,
        'ahu' => ['equipment_condition', 'voltage', 'current', 'supply_air_temp', 'return_air_temp', 'delta_t', 'fan_motor', 'filter_condition', 'drain_condition', 'insulation_condition', 'electrical_connections'],
        'tfa' => ['equipment_condition', 'voltage', 'current', 'ambient_temp', 'supply_air_temp', 'return_air_temp', 'delta_t', 'fan_motor', 'filter_condition', 'drain_condition', 'electrical_connections'],
        'chiller' => ['equipment_condition', 'suction_pressure', 'discharge_pressure', 'voltage', 'current', 'ambient_temp', 'water_in_temp', 'water_out_temp', 'delta_t', 'error_code', 'pcb_condition', 'compressor_condition', 'evaporator_condition', 'condenser_condition', 'refrigerant_leakage', 'insulation_condition', 'electrical_connections'],
        'exhaust_fan' => ['equipment_condition', 'voltage', 'current', 'fan_motor', 'electrical_connections'],
        'pump' => ['equipment_condition', 'voltage', 'current', 'fan_motor', 'insulation_condition', 'electrical_connections'],
        'electrical' => ['equipment_condition', 'voltage', 'current', 'pcb_condition', 'insulation_condition', 'electrical_connections'],
        'cooling_tower' => ['equipment_condition', 'voltage', 'current', 'ambient_temp', 'water_in_temp', 'water_out_temp', 'delta_t', 'fan_motor', 'drain_condition', 'electrical_connections'],
    ];

    /** @return array<string, string> */
    public static function options(string $list): array
    {
        return self::LISTS[$list] ?? throw new \InvalidArgumentException("Unknown complaint option list [{$list}].");
    }

    /** @return list<string> */
    public static function keys(string $list): array
    {
        return array_map('strval', array_keys(self::options($list)));
    }

    public static function label(string $list, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::LISTS[$list][$value] ?? $value;
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return array_map(fn (array $status) => $status['label'], self::STATUSES);
    }

    /** @return array{label: string, tone: string, icon: string} */
    public static function status(?string $status): array
    {
        return self::STATUSES[$status] ?? ['label' => str((string) $status)->headline()->toString(), 'tone' => 'secondary', 'icon' => 'circle'];
    }

    /** @return array<string, string> */
    public static function priorityOptions(): array
    {
        return array_map(fn (array $priority) => $priority['label'], self::PRIORITIES);
    }

    /** @return array<string, string> */
    public static function callTypeOptions(): array
    {
        return array_map(fn (array $type) => $type['label'], self::CALL_TYPES);
    }

    /** @return array{response: int, resolution: int} */
    public static function slaFor(?string $priority): array
    {
        $sla = self::PRIORITIES[$priority] ?? self::PRIORITIES['medium'];

        return ['response' => $sla['response'], 'resolution' => $sla['resolution']];
    }

    /** @return array{key: string, label: string, min: int, max: ?int, tone: string} */
    public static function ageBucket(int $days): array
    {
        foreach (self::AGE_BUCKETS as $bucket) {
            if ($bucket['max'] === null || $days <= $bucket['max']) {
                return $bucket;
            }
        }

        return self::AGE_BUCKETS[array_key_last(self::AGE_BUCKETS)];
    }

    public static function ageInDays(?Carbon $since): int
    {
        return $since ? (int) $since->copy()->startOfDay()->diffInDays(now()->startOfDay()) : 0;
    }

    /**
     * Diagnosis fields shown for an equipment type; unknown types get the full DX list.
     *
     * @return array<string, array{label: string, type: string, unit?: string}>
     */
    public static function diagnosisFieldsFor(?string $equipmentType): array
    {
        $keys = self::EQUIPMENT_FIELDS[$equipmentType] ?? self::DX_FIELDS;

        return array_intersect_key(self::DIAGNOSIS_FIELDS, array_flip($keys));
    }

    /** @return array<string, list<string>> */
    public static function equipmentFieldMap(): array
    {
        return self::EQUIPMENT_FIELDS + ['other' => self::DX_FIELDS];
    }

    /**
     * Admin-managed masters (nature of complaint, root causes, fault categories, pending reasons).
     *
     * @return array<string, string> code => label
     */
    public static function master(string $type): array
    {
        return ServiceMasterOption::query()->where('type', $type)->where('active', true)
            ->orderBy('sort_order')->orderBy('label')->pluck('label', 'code')->all();
    }

    /**
     * Active pending reasons grouped by pending group, each with its sub categories.
     *
     * @return array<string, list<array{code: string, label: string, subs: list<string>}>>
     */
    public static function pendingReasonTree(): array
    {
        $options = ServiceMasterOption::query()->whereIn('type', ['pending_reason', 'pending_sub_reason'])->where('active', true)
            ->orderBy('sort_order')->orderBy('label')->get();
        $subs = $options->where('type', 'pending_sub_reason')->groupBy(fn ($option) => data_get($option->metadata, 'reason'));
        $tree = [];
        foreach ($options->where('type', 'pending_reason') as $reason) {
            $tree[data_get($reason->metadata, 'group', 'other')][] = [
                'code' => $reason->code,
                'label' => $reason->label,
                'subs' => $subs->get($reason->code, collect())->pluck('label')->values()->all(),
            ];
        }

        return $tree;
    }

    /** Admin-manageable complaint master groups. @return array<string, array{label: string, hint: string}> */
    public static function masterGroups(): array
    {
        return [
            'complaint_nature' => ['label' => 'Nature of complaint', 'hint' => 'Shown when registering a complaint'],
            'fault_category' => ['label' => 'Fault category', 'hint' => 'Technician diagnosis'],
            'root_cause' => ['label' => 'Root cause', 'hint' => 'Visit report and closure'],
            'pending_reason' => ['label' => 'Pending reason', 'hint' => 'Grouped under a pending group'],
            'pending_sub_reason' => ['label' => 'Pending sub category', 'hint' => 'Detail under a pending reason'],
        ];
    }

    /**
     * Financial year label (April - March), e.g. 26-27.
     */
    public static function financialYear(?Carbon $date = null): string
    {
        $date ??= now();
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }
}
