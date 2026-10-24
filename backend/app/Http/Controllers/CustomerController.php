<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerEquipmentRequest;
use App\Http\Requests\StoreCustomerFloorPlanRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerRoom;
use App\Models\Product;
use App\Models\ServiceMasterOption;
use App\Models\User;
use App\Rules\IndianDistrict;
use App\Rules\IndianState;
use App\Services\IndiaLocations;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::with('group')->withCount(['contacts', 'branches', 'equipments'])
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->string('search').'%')
                    ->orWhere('code', 'like', '%'.$request->string('search').'%')
                    ->orWhere('city', 'like', '%'.$request->string('search').'%');
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('customers.create'), 403);

        return view('customers.create', [
            'groups' => CustomerGroup::where('status', 'active')->orderBy('name')->get(),
            'users' => User::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'group',
            'contacts.branch',
            'branches.contacts',
            'creditTerms',
            'documents',
            'floorPlans' => fn ($query) => $query->with(['branch', 'rooms.equipments.product', 'equipments.branch'])->orderBy('building_name')->orderBy('floor_number')->orderBy('id'),
            'equipments' => fn ($query) => $query->with(['branch', 'product', 'floorPlan', 'room'])->latest(),
            'serviceJobs' => fn ($query) => $query->with(['technician', 'serviceType', 'equipment'])->latest()->limit(10),
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'products' => Product::where('active', true)->orderBy('name')->get(),
            'equipmentTypes' => ServiceMasterOption::where('type', 'equipment_type')->where('active', true)->orderBy('sort_order')->get(),
            'equipmentCapacities' => ServiceMasterOption::where('type', 'ac_capacity')->where('active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(Request $request, Customer $customer): View
    {
        abort_unless($request->user()->can('customers.update'), 403);
        $customer->load('creditTerms');

        return view('customers.edit', [
            'customer' => $customer,
            'groups' => CustomerGroup::where('status', 'active')->orderBy('name')->get(),
            'users' => User::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->safe()->except('credit');
        $data['segments'] = array_values(array_filter($request->validated('segments') ?? []));

        DB::transaction(function () use ($request, $customer, $data): void {
            $customer->update($data);

            $credit = $request->validated('credit', []);
            foreach (['advance_required', 'tds_applicable', 'retention_applicable', 'po_mandatory', 'eway_bill_applicable'] as $field) {
                $credit[$field] = $request->boolean("credit.$field");
            }
            $customer->creditTerms()->updateOrCreate([], $credit);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'customer.updated',
                'subject_type' => Customer::class,
                'subject_id' => $customer->id,
                'properties' => ['code' => $customer->code, 'name' => $customer->name],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('customers.show', $customer)->with('success', "{$customer->name} updated successfully.");
    }

    public function storeFloorPlan(StoreCustomerFloorPlanRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $floorPlan = $customer->floorPlans()->create([
                'customer_branch_id' => $data['customer_branch_id'] ?? null,
                'name' => $data['name'],
                'floor_label' => $data['floor_label'] ?? null,
                'image_path' => $request->file('image')->store("customers/{$customer->id}/floor-plans", 'public'),
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'customer.floor_plan_added',
                'subject_type' => Customer::class,
                'subject_id' => $customer->id,
                'properties' => ['floor_plan_id' => $floorPlan->id, 'name' => $floorPlan->name],
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('customers.show', $customer)->with('success', 'Floor plan uploaded successfully.');
        }

        $buildingUuid = (string) Str::uuid();
        DB::transaction(function () use ($request, $customer, $data, $buildingUuid): void {
            foreach ($data['floors'] as $floorIndex => $floorData) {
                $floor = $customer->floorPlans()->create([
                    'customer_branch_id' => $data['customer_branch_id'] ?? null,
                    'building_uuid' => $buildingUuid,
                    'building_name' => $data['building_name'],
                    'name' => $data['building_name'].' · '.$floorData['name'],
                    'floor_label' => $floorData['name'],
                    'floor_number' => $floorIndex + 1,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                collect($floorData['rooms'])
                    ->map(fn (string $room): string => trim($room))
                    ->filter()
                    ->unique(fn (string $room): string => Str::lower($room))
                    ->values()
                    ->each(fn (string $room, int $roomIndex) => $floor->rooms()->create([
                        'customer_id' => $customer->id,
                        'name' => $room,
                        'sort_order' => $roomIndex,
                    ]));
            }

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'customer.building_layout_added',
                'subject_type' => Customer::class,
                'subject_id' => $customer->id,
                'properties' => ['building_uuid' => $buildingUuid, 'name' => $data['building_name'], 'floors' => count($data['floors'])],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('customers.show', $customer)->with('success', 'Building floors and rooms created successfully.');
    }

    public function storeEquipment(StoreCustomerEquipmentRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();
        if (! empty($data['customer_room_id'])) {
            $room = CustomerRoom::with('floor')->findOrFail($data['customer_room_id']);
            $data['customer_floor_plan_id'] = $room->customer_floor_plan_id;
            $data['customer_branch_id'] = $room->floor->customer_branch_id;
            $data['location'] = $room->name;
            $data['plan_x'] = null;
            $data['plan_y'] = null;
        }

        $equipment = $customer->equipments()->create($data);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => 'customer.equipment_added',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'properties' => [
                'equipment_id' => $equipment->id,
                'equipment_type' => $equipment->equipment_type,
                'serial_no' => $equipment->serial_no,
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('customers.show', $customer)->with('success', 'AC unit added successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('customers.create'), 403);
        $this->forceIndianCountry($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_group_id' => ['required', Rule::exists('customer_groups', 'id')->where('status', 'active')],
            'mother_tongue' => ['nullable', 'string', 'max:50'],
            'types' => ['required', 'array', 'size:1'],
            'types.*' => [Rule::in(['individual', 'corporate', 'architect', 'consultant', 'agent', 'dealer', 'government'])],
            'segments' => ['nullable', 'array', 'max:1'],
            'segments.*' => ['nullable', Rule::in(['healthcare', 'education', 'industrials', 'builder', 'hospitality', 'office'])],
            'priority' => ['required', Rule::in(['normal', 'high', 'very_high'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'blacklisted'])],
            'branch_type' => ['required', Rule::in(['single', 'multi'])],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120', new IndianState],
            'district' => ['required', 'string', 'max:120', new IndianDistrict],
            'pin_code' => ['required', 'regex:/^[0-9]{6}$/'],
            'country' => ['required', Rule::in([IndiaLocations::COUNTRY])],
            'email_1' => ['required_without:email_2', 'nullable', 'email'],
            'email_2' => ['nullable', 'email'],
            'gstin' => ['nullable', 'regex:/^[0-9A-Za-z]{15}$/'],
            'pan' => ['nullable', 'regex:/^[0-9A-Za-z]{10}$/'],
            'contact_no_1' => ['required_without:contact_no_2', 'nullable', 'string', 'max:20'],
            'contact_no_2' => ['nullable', 'string', 'max:20'],
            'classification' => ['nullable', Rule::in(['strategic', 'regular', 'transactional', 'one_time'])],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['string', 'max:60'],
            'business_potential' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'site_access_instructions' => ['nullable', 'string', 'max:2000'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'gst_registration_type' => ['nullable', Rule::in(['regular', 'composition', 'unregistered'])],
            'tan' => ['nullable', 'regex:/^[A-Za-z]{4}[0-9]{5}[A-Za-z]$/'],
            'tds_percent' => ['nullable', 'numeric', 'between:0,100'],
            'udyam_no' => ['nullable', 'string', 'max:40'],
            'msme_type' => ['nullable', Rule::in(['micro', 'small', 'medium'])],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'sales_person_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'credit' => ['nullable', 'array'],
            'credit.credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'credit.credit_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'credit.payment_mode' => ['nullable', 'string', 'max:40'],
            'credit.advance_required' => ['nullable', 'boolean'],
            'credit.tds_applicable' => ['nullable', 'boolean'],
            'credit.retention_applicable' => ['nullable', 'boolean'],
            'credit.retention_percent' => ['nullable', 'numeric', 'between:0,100'],
            'credit.billing_cycle' => ['nullable', 'string', 'max:40'],
            'credit.invoice_submission_method' => ['nullable', 'string', 'max:40'],
            'credit.po_mandatory' => ['nullable', 'boolean'],
            'credit.eway_bill_applicable' => ['nullable', 'boolean'],
            'credit.default_payment_terms' => ['nullable', 'string', 'max:2000'],
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.designation' => ['nullable', 'string', 'max:120'],
            'contacts.*.department' => ['nullable', 'string', 'max:120'],
            'contacts.*.email' => ['nullable', 'email'],
            'contacts.*.mother_tongue' => ['nullable', 'string', 'max:50'],
            'contacts.*.phone' => ['nullable', 'string', 'max:20'],
            'contacts.*.whatsapp' => ['nullable', 'string', 'max:20'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
            'contacts.*.is_service' => ['nullable', 'boolean'],
            'contacts.*.is_billing' => ['nullable', 'boolean'],
            'contacts.*.is_escalation' => ['nullable', 'boolean'],
            'branches' => [Rule::requiredIf($request->input('branch_type') === 'multi'), 'nullable', 'array', 'min:1'],
            'branches.*.name' => ['required_with:branches', 'string', 'max:255'],
            'branches.*.gstin' => ['nullable', 'regex:/^[0-9A-Za-z]{15}$/'],
            'branches.*.gst_registration_type' => ['nullable', Rule::in(['regular', 'composition', 'unregistered'])],
            'branches.*.address_line_1' => ['required_with:branches', 'string', 'max:255'],
            'branches.*.address_line_2' => ['nullable', 'string', 'max:255'],
            'branches.*.landmark' => ['nullable', 'string', 'max:255'],
            'branches.*.area' => ['nullable', 'string', 'max:255'],
            'branches.*.city' => ['required_with:branches', 'string', 'max:120'],
            'branches.*.state' => ['required_with:branches', 'string', 'max:120', new IndianState],
            'branches.*.district' => ['required_with:branches', 'string', 'max:120', new IndianDistrict],
            'branches.*.pin_code' => ['required_with:branches', 'regex:/^[0-9]{6}$/'],
            'branches.*.country' => ['required_with:branches', Rule::in([IndiaLocations::COUNTRY])],
            'branches.*.contact_no_1' => ['nullable', 'string', 'max:20'],
            'branches.*.contact_no_2' => ['nullable', 'string', 'max:20'],
            'branches.*.email_1' => ['nullable', 'email'],
            'branches.*.email_2' => ['nullable', 'email'],
            'branches.*.billing_address' => ['nullable', 'string', 'max:2000'],
            'branches.*.site_access_instructions' => ['nullable', 'string', 'max:2000'],
            'branches.*.contacts' => ['nullable', 'array'],
            'branches.*.contacts.*.name' => ['nullable', 'string', 'max:255'],
            'branches.*.contacts.*.designation' => ['nullable', 'string', 'max:120'],
            'branches.*.contacts.*.department' => ['nullable', 'string', 'max:120'],
            'branches.*.contacts.*.phone' => ['nullable', 'string', 'max:20'],
            'branches.*.contacts.*.whatsapp' => ['nullable', 'string', 'max:20'],
            'branches.*.contacts.*.email' => ['nullable', 'email'],
            'branches.*.contacts.*.mother_tongue' => ['nullable', 'string', 'max:50'],
            'branches.*.contacts.*.is_primary' => ['nullable', 'boolean'],
            'branches.*.contacts.*.is_service' => ['nullable', 'boolean'],
            'branches.*.contacts.*.is_billing' => ['nullable', 'boolean'],
            'branches.*.contacts.*.is_escalation' => ['nullable', 'boolean'],
            'documents.*' => ['nullable', 'file', 'max:10240'],
        ], [
            'types.size' => 'Select exactly one customer type.',
            'segments.max' => 'Select no more than one segment.',
        ]);

        $primaryCount = collect($request->input('contacts', []))->filter(fn ($contact) => ! empty($contact['is_primary']))->count();
        if ($primaryCount > 1) {
            return back()->withInput()->withErrors(['contacts' => 'Only one primary contact is allowed.']);
        }

        foreach ($validated['branches'] ?? [] as $branchIndex => $branch) {
            $contacts = collect($branch['contacts'] ?? [])->filter(fn ($contact) => collect($contact)->filter()->isNotEmpty());
            foreach ($contacts as $contactIndex => $contact) {
                if (blank($contact['name'] ?? null)) {
                    return back()->withInput()->withErrors([
                        "branches.$branchIndex.contacts.$contactIndex.name" => 'A contact name is required when branch contact details are entered.',
                    ]);
                }
            }
            if ($contacts->filter(fn ($contact) => ! empty($contact['is_primary']))->count() > 1) {
                return back()->withInput()->withErrors([
                    "branches.$branchIndex.contacts" => 'Only one primary contact is allowed per branch.',
                ]);
            }
        }

        $duplicate = Customer::where('name', $validated['name'])
            ->where('city', $request->input('city'))
            ->exists();
        if ($duplicate && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->withErrors(['name' => 'A customer with the same name and city already exists. Confirm duplicate to continue.']);
        }

        $validated['segments'] = array_values(array_filter($validated['segments'] ?? []));

        $customer = DB::transaction(function () use ($request, $validated) {
            $nextId = (Customer::withTrashed()->max('id') ?? 0) + 1;
            $customer = Customer::create(array_merge(
                collect($validated)->only([
                    'customer_group_id', 'name', 'mother_tongue', 'types', 'segments', 'priority', 'status', 'branch_type',
                    'classification', 'sources', 'business_potential', 'address_line_1', 'address_line_2',
                    'landmark', 'area', 'city', 'district', 'state', 'pin_code', 'country', 'latitude',
                    'longitude', 'contact_no_1', 'contact_no_2', 'email_1', 'email_2',
                    'site_access_instructions', 'billing_address', 'remarks', 'gstin', 'pan',
                    'gst_registration_type', 'tan', 'tds_percent', 'udyam_no', 'msme_type',
                    'owner_id', 'sales_person_id', 'manager_id',
                ])->all(),
                ['code' => 'CUST'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT), 'created_by' => $request->user()->id]
            ));

            foreach ($validated['contacts'] as $order => $contact) {
                $customer->contacts()->create(array_merge($contact, ['sort_order' => $order]));
            }

            $branches = $request->input('branch_type') === 'multi' ? ($validated['branches'] ?? []) : [];
            foreach ($branches as $index => $branch) {
                $branchContacts = $branch['contacts'] ?? [];
                unset($branch['contacts']);
                $branchModel = $customer->branches()->create(array_merge($branch, [
                    'code' => $customer->code.'-B'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                ]));

                foreach ($branchContacts as $order => $contact) {
                    if (collect($contact)->filter()->isEmpty()) {
                        continue;
                    }
                    $customer->contacts()->create(array_merge($contact, [
                        'customer_branch_id' => $branchModel->id,
                        'sort_order' => $order,
                    ]));
                }
            }

            $credit = $validated['credit'] ?? [];
            $customer->creditTerms()->create(array_merge($credit, [
                'credit_limit' => (float) ($credit['credit_limit'] ?? 0),
                'credit_days' => (int) ($credit['credit_days'] ?? 0),
                'retention_percent' => (float) ($credit['retention_percent'] ?? 0),
                'advance_required' => (bool) ($credit['advance_required'] ?? false),
                'tds_applicable' => (bool) ($credit['tds_applicable'] ?? false),
                'retention_applicable' => (bool) ($credit['retention_applicable'] ?? false),
                'po_mandatory' => (bool) ($credit['po_mandatory'] ?? false),
                'eway_bill_applicable' => (bool) ($credit['eway_bill_applicable'] ?? false),
            ]));

            foreach ($request->file('documents', []) as $index => $file) {
                $customer->documents()->create([
                    'type' => $request->input("document_types.$index", 'Other'),
                    'name' => $file->getClientOriginalName(),
                    'path' => $file->store('customers/'.$customer->id, 'public'),
                    'uploaded_by' => $request->user()->id,
                ]);
            }

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'customer.created',
                'subject_type' => Customer::class,
                'subject_id' => $customer->id,
                'properties' => ['code' => $customer->code, 'name' => $customer->name],
                'ip_address' => $request->ip(),
            ]);

            return $customer;
        });

        return redirect()->route('customers.index')->with('success', "{$customer->name} created successfully.");
    }

    /** Addresses are India-only, so the country is always stored as India whatever was posted. */
    private function forceIndianCountry(Request $request): void
    {
        $request->merge(['country' => IndiaLocations::COUNTRY]);
        if (is_array($branches = $request->input('branches'))) {
            $request->merge(['branches' => array_map(
                fn (mixed $branch): mixed => is_array($branch) ? ['country' => IndiaLocations::COUNTRY] + $branch : $branch,
                $branches,
            )]);
        }
    }
}
