<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('companies.read'), 403);

        return view('companies.index', [
            'companies' => Company::query()->with(['parent', 'sites'])->withCount(['branches', 'sites'])->orderByRaw("CASE WHEN company_type = 'head_office' THEN 1 ELSE 2 END")->orderBy('legal_name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('companies.create'), 403);

        return view('companies.form', ['company' => null, 'parents' => Company::where('company_type', 'head_office')->orderBy('legal_name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('companies.create'), 403);
        $validated = $this->validateCompany($request);
        $company = DB::transaction(function () use ($request, $validated): Company {
            $company = Company::create($this->payload($request, $validated) + ['created_by' => $request->user()->id]);
            $this->syncSites($company, $validated['sites'] ?? []);

            return $company;
        });

        return redirect()->route('companies.index')->with('success', $company->legal_name.' added to company master.');
    }

    public function edit(Request $request, Company $company): View
    {
        abort_unless($request->user()->can('companies.update'), 403);

        return view('companies.form', ['company' => $company->load('sites'), 'parents' => Company::where('company_type', 'head_office')->where('id', '!=', $company->id)->orderBy('legal_name')->get()]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->can('companies.update'), 403);
        $validated = $this->validateCompany($request, $company);
        DB::transaction(function () use ($request, $company, $validated): void {
            $company->update($this->payload($request, $validated, $company));
            $this->syncSites($company, $validated['sites'] ?? []);
        });

        return redirect()->route('companies.index')->with('success', 'Company master updated.');
    }

    /** @return array<string, mixed> */
    private function validateCompany(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'exists:companies,id'],
            'organization_id' => ['required', 'string', 'max:50', Rule::unique('companies', 'organization_id')->ignore($company)],
            'code' => ['required', 'string', 'max:50', Rule::unique('companies', 'code')->ignore($company)],
            'short_name' => ['required', 'string', 'max:100'],
            'legal_name' => ['required', 'string', 'max:255'],
            'company_type' => ['required', Rule::in(['head_office', 'branch'])],
            'organization_type' => ['nullable', 'string', 'max:50'],
            'industry' => ['required', 'string', 'max:80'],
            'business_models' => ['required', 'array', 'min:1'],
            'business_models.*' => ['string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'incorporation_date' => ['nullable', 'date'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'payment_scanner' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'digital_signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'address' => ['nullable', 'array'],
            'contacts' => ['nullable', 'array'],
            'localization' => ['nullable', 'array'],
            'invoice_settings' => ['nullable', 'array'],
            'compliance' => ['nullable', 'array'],
            'banking' => ['nullable', 'array'],
            'sites' => ['nullable', 'array'],
            'sites.*.site_id' => ['required_with:sites.*.name', 'nullable', 'string', 'max:60'],
            'sites.*.code' => ['required_with:sites.*.name', 'nullable', 'string', 'max:60'],
            'sites.*.name' => ['nullable', 'string', 'max:255'],
            'sites.*.type' => ['nullable', Rule::in(['additional_place', 'warehouse', 'workshop', 'counter', 'office'])],
            'sites.*.address' => ['nullable', 'array'],
            'sites.*.contacts' => ['nullable', 'array'],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, array $validated, ?Company $company = null): array
    {
        return [
            'parent_id' => $validated['company_type'] === 'branch' ? ($validated['parent_id'] ?? null) : null,
            'organization_id' => $validated['organization_id'],
            'code' => $validated['code'],
            'short_name' => $validated['short_name'],
            'legal_name' => $validated['legal_name'],
            'company_type' => $validated['company_type'],
            'organization_type' => $validated['organization_type'] ?? null,
            'industry' => $validated['industry'],
            'business_models' => $validated['business_models'],
            'status' => $validated['status'],
            'incorporation_date' => $validated['incorporation_date'] ?? null,
            'logo_path' => $request->file('logo')?->store('company/logo', 'public') ?? $company?->logo_path ?? 'images/brand/classic-logo.jpeg',
            'address' => $validated['address'] ?? [],
            'contacts' => $validated['contacts'] ?? [],
            'localization' => $validated['localization'] ?? [],
            'invoice_settings' => $validated['invoice_settings'] ?? [],
            'compliance' => $validated['compliance'] ?? [],
            'banking' => $validated['banking'] ?? [],
            'payment_scanner_path' => $request->file('payment_scanner')?->store('company/payment', 'public') ?? $company?->payment_scanner_path,
            'digital_signature_path' => $request->file('digital_signature')?->store('company/signature', 'public') ?? $company?->digital_signature_path,
        ];
    }

    /** @param array<int, array<string, mixed>> $sites */
    private function syncSites(Company $company, array $sites): void
    {
        $company->sites()->delete();
        foreach ($sites as $site) {
            if (blank($site['name'] ?? null)) {
                continue;
            }
            $company->sites()->create([
                'site_id' => $site['site_id'],
                'code' => $site['code'],
                'name' => $site['name'],
                'type' => $site['type'] ?? 'additional_place',
                'address' => $site['address'] ?? [],
                'contacts' => $site['contacts'] ?? [],
                'active' => true,
            ]);
        }
    }
}
