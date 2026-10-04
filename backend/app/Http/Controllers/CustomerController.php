<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerGroup;
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::in(['individual', 'corporate', 'architect', 'consultant', 'agent', 'dealer', 'government'])],
            'segments' => ['nullable', 'array'],
            'priority' => ['required', Rule::in(['normal', 'high', 'very_high'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'blacklisted'])],
            'branch_type' => ['required', Rule::in(['single', 'multi'])],
            'pin_code' => ['nullable', 'regex:/^[0-9]{6}$/'],
            'email_1' => ['required_without:email_2', 'nullable', 'email'],
            'email_2' => ['nullable', 'email'],
            'gstin' => ['nullable', 'size:15'],
            'pan' => ['nullable', 'size:10'],
            'contact_no_1' => ['required_without:contact_no_2', 'nullable', 'string', 'max:20'],
            'contact_no_2' => ['nullable', 'string', 'max:20'],
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email'],
            'contacts.*.phone' => ['nullable', 'string', 'max:20'],
            'branches' => [Rule::requiredIf($request->input('branch_type') === 'multi'), 'array'],
            'branches.*.name' => ['required_with:branches', 'string', 'max:255'],
            'documents.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $primaryCount = collect($request->input('contacts', []))->filter(fn ($contact) => ! empty($contact['is_primary']))->count();
        if ($primaryCount > 1) {
            return back()->withInput()->withErrors(['contacts' => 'Only one primary contact is allowed.']);
        }

        $duplicate = Customer::where('name', $validated['name'])
            ->where('city', $request->input('city'))
            ->exists();
        if ($duplicate && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->withErrors(['name' => 'A customer with the same name and city already exists. Confirm duplicate to continue.']);
        }

        $customer = DB::transaction(function () use ($request) {
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

            foreach ($request->input('contacts', []) as $order => $contact) {
                $customer->contacts()->create(array_merge($contact, ['sort_order' => $order]));
            }

            foreach ($request->input('branches', []) as $index => $branch) {
                $customer->branches()->create(array_merge($branch, [
                    'code' => $customer->code.'-B'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                ]));
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
