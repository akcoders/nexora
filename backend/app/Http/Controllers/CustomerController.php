<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerEquipmentRequest;
use App\Http\Requests\StoreCustomerFloorPlanRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ServiceMasterOption;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::withCount(['contacts', 'branches'])
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

    public function create(): View
    {
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
            'floorPlans' => fn ($query) => $query->with(['branch', 'equipments.branch'])->latest(),
            'equipments' => fn ($query) => $query->with(['branch', 'product', 'floorPlan'])->latest(),
            'serviceJobs' => fn ($query) => $query->with(['technician', 'serviceType', 'equipment'])->latest()->limit(10),
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'products' => Product::where('active', true)->orderBy('name')->get(),
            'equipmentTypes' => ServiceMasterOption::where('type', 'equipment_type')->where('active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function storeFloorPlan(StoreCustomerFloorPlanRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();
        unset($data['image']);

        $floorPlan = $customer->floorPlans()->create(array_merge($data, [
            'image_path' => $request->file('image')->store("customers/{$customer->id}/floor-plans", 'public'),
            'created_by' => $request->user()->id,
        ]));

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

    public function storeEquipment(StoreCustomerEquipmentRequest $request, Customer $customer): RedirectResponse
    {
        $equipment = $customer->equipments()->create($request->validated());

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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::in(['individual', 'corporate', 'architect', 'consultant', 'agent', 'dealer', 'government'])],
            'segments' => ['nullable', 'array'],
            'segments.*' => [Rule::in(['healthcare', 'education', 'industrials', 'builder', 'hospitality', 'office'])],
            'priority' => ['required', Rule::in(['normal', 'high', 'very_high'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'blacklisted'])],
            'branch_type' => ['required', Rule::in(['single', 'multi'])],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'pin_code' => ['required', 'regex:/^[0-9]{6}$/'],
            'country' => ['required', 'string', 'max:120'],
            'email_1' => ['required_without:email_2', 'nullable', 'email'],
            'email_2' => ['nullable', 'email'],
            'gstin' => ['nullable', 'regex:/^[0-9A-Za-z]{15}$/'],
            'pan' => ['nullable', 'regex:/^[0-9A-Za-z]{10}$/'],
            'contact_no_1' => ['required_without:contact_no_2', 'nullable', 'string', 'max:20'],
            'contact_no_2' => ['nullable', 'string', 'max:20'],
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.designation' => ['nullable', 'string', 'max:120'],
            'contacts.*.department' => ['nullable', 'string', 'max:120'],
            'contacts.*.email' => ['nullable', 'email'],
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
            'branches.*.district' => ['nullable', 'string', 'max:120'],
            'branches.*.state' => ['required_with:branches', 'string', 'max:120'],
            'branches.*.pin_code' => ['required_with:branches', 'regex:/^[0-9]{6}$/'],
            'branches.*.country' => ['required_with:branches', 'string', 'max:120'],
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
            'branches.*.contacts.*.is_primary' => ['nullable', 'boolean'],
            'branches.*.contacts.*.is_service' => ['nullable', 'boolean'],
            'branches.*.contacts.*.is_billing' => ['nullable', 'boolean'],
            'branches.*.contacts.*.is_escalation' => ['nullable', 'boolean'],
            'documents.*' => ['nullable', 'file', 'max:10240'],
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

        $customer = DB::transaction(function () use ($request, $validated) {
            $nextId = (Customer::withTrashed()->max('id') ?? 0) + 1;
            $customer = Customer::create(array_merge(
                $request->only([
                    'customer_group_id', 'name', 'types', 'segments', 'priority', 'status', 'branch_type',
                    'classification', 'sources', 'business_potential', 'address_line_1', 'address_line_2',
                    'landmark', 'area', 'city', 'district', 'state', 'pin_code', 'country', 'latitude',
                    'longitude', 'contact_no_1', 'contact_no_2', 'email_1', 'email_2',
                    'site_access_instructions', 'billing_address', 'remarks', 'gstin', 'pan',
                    'gst_registration_type', 'tan', 'tds_percent', 'udyam_no', 'msme_type',
                    'owner_id', 'sales_person_id', 'manager_id',
                ]),
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

            $customer->creditTerms()->create($request->input('credit', []));

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
}
