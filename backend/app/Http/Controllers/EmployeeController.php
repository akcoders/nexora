<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Premises;
use App\Models\User;
use App\Rules\AadhaarNumber;
use App\Rules\IndianMobile;
use App\Services\EmployeeMasterOptions;
use App\Services\IndiaLocations;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('employees.read'), 403);

        $employees = User::query()
            ->with(['employeeProfile', 'roles', 'premises'])
            ->whereHas('employeeProfile')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($query) => $query->where('name', 'like', $search)
                    ->orWhere('employee_code', 'like', $search)
                    ->orWhere('email', 'like', $search));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('department'), fn ($query) => $query->where('department', $request->string('department')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'departments' => User::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'metrics' => [
                'total' => User::whereHas('employeeProfile')->count(),
                'active' => User::whereHas('employeeProfile')->where('status', 'active')->count(),
                'probation' => User::whereHas('employeeProfile', fn ($query) => $query->where('employment_type', 'probation'))->count(),
                'payroll' => EmployeeProfile::where('gross_monthly_salary', '>', 0)->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('employees.create'), 403);

        return view('employees.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('employees.create'), 403);
        $validated = $this->validateEmployee($request);
        $temporaryPassword = Str::password(12);

        $user = DB::transaction(function () use ($request, $validated, $temporaryPassword): User {
            $user = User::create([
                'name' => $this->fullName($validated),
                'email' => $validated['work_email'],
                'password' => Hash::make($temporaryPassword),
                'phone' => $validated['phone'] ?? null,
                'employee_code' => ($validated['employee_code'] ?? null) ?: $this->nextEmployeeCode(),
                'department' => $validated['department'] ?? null,
                'designation' => $validated['designation'] ?? null,
                'status' => $validated['user_status'],
                'preferred_language' => $this->preferredLanguage($validated),
            ]);
            $user->assignRole($validated['role']);
            $user->premises()->sync($validated['premises'] ?? []);
            $user->employeeProfile()->create($this->profilePayload($request, $validated) + [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->syncLeaveBalances($user);

            return $user;
        });

        return redirect()->route('employees.show', $user)
            ->with('success', 'Employee and user account created successfully.')
            ->with('temporary_password', $temporaryPassword);
    }

    public function show(Request $request, User $employee): View
    {
        abort_unless($request->user()->can('employees.read'), 403);
        abort_unless($employee->employeeProfile()->exists(), 404);

        return view('employees.show', [
            'employee' => $employee->load(['employeeProfile.reportingManager', 'roles', 'premises', 'leaveBalances.leaveType']),
            'recentLeaves' => $employee->leaveRequests()->with('leaveType')->latest()->limit(5)->get(),
            'recentVouchers' => $employee->expenseVouchers()->latest()->limit(5)->get(),
            'recentPayroll' => $employee->payrollEntries()->with('payrollRun')->latest()->limit(6)->get(),
        ]);
    }

    public function edit(Request $request, User $employee): View
    {
        abort_unless($request->user()->can('employees.update'), 403);
        abort_unless($employee->employeeProfile()->exists(), 404);

        return view('employees.form', $this->formData($employee->load(['employeeProfile', 'premises', 'roles'])));
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        abort_unless($request->user()->can('employees.update'), 403);
        abort_unless($employee->employeeProfile()->exists(), 404);
        $validated = $this->validateEmployee($request, $employee);

        DB::transaction(function () use ($request, $employee, $validated): void {
            $employee->update([
                'name' => $this->fullName($validated),
                'email' => $validated['work_email'],
                'phone' => $validated['phone'] ?? null,
                'employee_code' => ($validated['employee_code'] ?? null) ?: ($employee->employee_code ?: $this->nextEmployeeCode()),
                'department' => $validated['department'] ?? null,
                'designation' => $validated['designation'] ?? null,
                'status' => $validated['user_status'],
                'preferred_language' => $this->preferredLanguage($validated),
            ]);
            $employee->syncRoles([$validated['role']]);
            $employee->premises()->sync($validated['premises'] ?? []);
            $employee->employeeProfile->update($this->profilePayload($request, $validated, $employee->employeeProfile) + [
                'updated_by' => $request->user()->id,
            ]);
            $this->syncLeaveBalances($employee);
        });

        return redirect()->route('employees.show', $employee)->with('success', 'Employee master updated successfully.');
    }

    /** @return array<string, mixed> */
    private function validateEmployee(Request $request, ?User $employee = null): array
    {
        $this->normaliseEmployeeInput($request);
        $profile = $employee?->employeeProfile;
        $saved = fn (string $key): ?string => is_scalar($value = data_get($profile, $key)) ? (string) $value : null;
        $in = fn (string $list, ?string $key = null): In => Rule::in(EmployeeMasterOptions::allowed($list, $key ? $saved($key) : null));
        $personName = 'regex:/^[\pL\pM][\pL\pM\s.\'’-]*$/u';
        $notSelf = $employee ? [Rule::notIn([$employee->id])] : [];
        $money = ['nullable', 'numeric', 'min:0', 'max:10000000', 'decimal:0,2'];
        $mobile = new IndianMobile;
        $addressKeys = 'array:line1,state,district,city,pin,country';

        $rules = [
            'first_name' => ['required', 'string', 'min:2', 'max:120', $personName],
            'middle_name' => ['nullable', 'string', 'max:120', $personName],
            'last_name' => ['nullable', 'string', 'max:120', $personName],
            'local_script_name' => ['nullable', 'string', 'max:255'],
            'salutation' => ['nullable', $in('salutations', 'salutation')],
            'gender' => ['nullable', $in('genders')],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString(), 'after_or_equal:'.now()->subYears(80)->toDateString()],
            'marital_status' => ['nullable', $in('marital_statuses')],
            'anniversary_date' => ['exclude_unless:marital_status,married', 'nullable', 'date', 'before_or_equal:today', 'after:date_of_birth'],
            'blood_group' => ['nullable', $in('blood_groups', 'blood_group')],
            'nationality' => ['nullable', 'string', 'max:80', $in('nationalities', 'nationality')],
            'religion' => ['nullable', 'string', 'max:80', $in('religions', 'religion')],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', Rule::in(EmployeeMasterOptions::languageNames())],
            'preferred_language' => ['nullable', 'string', Rule::in(EmployeeMasterOptions::languageNames())],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'work_email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($employee)],
            'personal_email' => ['nullable', 'email', 'max:254', 'different:work_email'],
            'phone' => ['required', 'string', 'max:20', $mobile],
            'employee_code' => ['nullable', 'string', 'max:30', 'alpha_dash', Rule::unique('users', 'employee_code')->ignore($employee)],
            'department' => ['required', 'string', 'max:100', Rule::in(EmployeeMasterOptions::allowed(array_keys(EmployeeMasterOptions::departments()), $employee?->department))],
            'designation' => ['required', 'string', 'max:100', Rule::in(EmployeeMasterOptions::allowed(EmployeeMasterOptions::designations(), $employee?->designation))],
            'role' => ['required', 'exists:roles,name'],
            'user_status' => ['required', $in('user_statuses')],
            'premises' => ['required', 'array', 'min:1'],
            'premises.*' => ['integer', 'distinct', 'exists:premises,id'],
            'date_of_joining' => ['nullable', 'date', 'after:date_of_birth', 'before_or_equal:'.now()->addYear()->toDateString()],
            'employment_type' => ['nullable', $in('employment_types', 'employment_type')],
            'employee_category' => ['nullable', 'string', 'max:80', $in('employee_categories', 'employee_category')],
            'job_grade' => ['nullable', 'string', 'max:30', $in('job_grades', 'job_grade')],
            'reporting_manager_id' => ['nullable', 'integer', 'exists:users,id', ...$notSelf],
            'functional_manager_id' => ['nullable', 'integer', 'exists:users,id', ...$notSelf],
            'hr_manager_id' => ['nullable', 'integer', 'exists:users,id', ...$notSelf],
            'salary_currency' => ['required', 'string', 'max:10', $in('currencies', 'salary_currency')],
            'pay_frequency' => ['required', $in('pay_frequencies')],
            'pay_group' => ['nullable', 'string', 'max:50', $in('pay_groups', 'pay_group')],
            'basic_salary' => $money,
            'hra' => $money,
            'transport_allowance' => $money,
            'fuel_allowance' => $money,
            'other_allowance' => $money,
            'gross_monthly_salary' => [...$money, function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if ((float) $value > 0 && (float) $value < (float) $request->input('basic_salary', 0)) {
                    $fail('Gross monthly salary cannot be less than the basic salary. Leave it 0 to auto-total the components.');
                }
            }],
            'identity_documents' => ['nullable', 'array:aadhaar,pan,passport,driving_licence,voter_id'],
            'identity_documents.aadhaar' => ['nullable', 'string', new AadhaarNumber],
            'identity_documents.pan' => ['nullable', 'string', 'regex:/^[A-Z]{3}[ABCFGHLJPTK][A-Z][0-9]{4}[A-Z]$/'],
            'identity_documents.passport' => ['nullable', 'string', 'regex:/^[A-PR-WY][1-9][0-9]{5}[1-9]$/'],
            'identity_documents.driving_licence' => ['nullable', 'string', 'regex:/^[A-Z]{2}[0-9]{2}[0-9]{4}[0-9]{7}$/'],
            'identity_documents.voter_id' => ['nullable', 'string', 'regex:/^[A-Z]{3}[0-9]{7}$/'],
            'current_address' => ['nullable', $addressKeys],
            'permanent_address' => ['nullable', $addressKeys],
            'company_accommodation_address' => ['nullable', $addressKeys],
            'emergency_family' => ['nullable', 'array:emergency_name,relation,mobile'],
            'emergency_family.emergency_name' => ['nullable', 'string', 'max:120', $personName, 'required_with:emergency_family.mobile'],
            'emergency_family.relation' => ['nullable', 'string', $in('relations', 'emergency_family.relation'), 'required_with:emergency_family.emergency_name'],
            'emergency_family.mobile' => ['nullable', 'string', 'max:20', $mobile, 'different:phone', 'required_with:emergency_family.emergency_name'],
            'employment_details' => ['nullable', 'array'],
            'verification' => ['nullable', 'array:police_verification,police_verification_date,police_verification_reference,police_verification_remarks,background_check,background_check_date,background_check_agency,background_check_remarks,uan,esic'],
            'verification.police_verification' => ['nullable', Rule::in(EmployeeMasterOptions::verificationStatusKeys())],
            'verification.police_verification_date' => ['nullable', 'date', 'before_or_equal:today', 'required_if:verification.police_verification,verified'],
            'verification.police_verification_reference' => ['nullable', 'string', 'max:120'],
            'verification.police_verification_remarks' => ['nullable', 'string', 'max:1000'],
            'verification.background_check' => ['nullable', Rule::in(EmployeeMasterOptions::verificationStatusKeys())],
            'verification.background_check_date' => ['nullable', 'date', 'before_or_equal:today', 'required_if:verification.background_check,verified'],
            'verification.background_check_agency' => ['nullable', 'string', 'max:120'],
            'verification.background_check_remarks' => ['nullable', 'string', 'max:1000'],
            'verification.uan' => ['nullable', 'string', 'regex:/^[0-9]{12}$/'],
            'verification.esic' => ['nullable', 'string', 'regex:/^([0-9]{10}|[0-9]{17})$/'],
            'compensation' => ['nullable', 'array'],
            'bank_details' => ['nullable', 'array:bank_name,account_number,ifsc'],
            'bank_details.bank_name' => ['nullable', 'string', 'max:120', 'required_with:bank_details.account_number,bank_details.ifsc'],
            'bank_details.account_number' => ['nullable', 'string', 'regex:/^[0-9]{9,18}$/', 'required_with:bank_details.bank_name,bank_details.ifsc'],
            'bank_details.ifsc' => ['nullable', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/', 'required_with:bank_details.bank_name,bank_details.account_number'],
            'benefits_allowances' => ['nullable', 'array:pf_eligible,esic_eligible,insurance_policy,gratuity,loan_balance,bonus_plan,medical_benefit,meal_benefit'],
            'benefits_allowances.pf_eligible' => ['nullable', $in('yes_no')],
            'benefits_allowances.esic_eligible' => ['nullable', $in('yes_no')],
            'benefits_allowances.gratuity' => ['nullable', 'string', $in('yes_no', 'benefits_allowances.gratuity')],
            'benefits_allowances.loan_balance' => $money,
            'benefits_allowances.insurance_policy' => ['nullable', 'string', 'max:120'],
            'benefits_allowances.bonus_plan' => ['nullable', 'string', 'max:120'],
            'benefits_allowances.medical_benefit' => ['nullable', 'string', 'max:120'],
            'benefits_allowances.meal_benefit' => ['nullable', 'string', 'max:120'],
            'education_skills' => ['nullable', 'array:highest_qualification,specialization,technical_skills,certifications,trade_licence,experience_years'],
            'education_skills.highest_qualification' => ['nullable', 'string', $in('qualifications', 'education_skills.highest_qualification')],
            'education_skills.specialization' => ['nullable', 'string', 'max:255'],
            'education_skills.technical_skills' => ['nullable', 'string', 'max:255'],
            'education_skills.certifications' => ['nullable', 'string', 'max:255'],
            'education_skills.trade_licence' => ['nullable', 'string', 'max:255'],
            'education_skills.experience_years' => ['nullable', 'numeric', 'min:0', 'max:60', 'multiple_of:0.5'],
            'safety' => ['nullable', 'array:ppe_size,safety_training,medical_fitness,incident_history'],
            'safety.ppe_size' => ['nullable', 'string', $in('ppe_sizes', 'safety.ppe_size')],
            'safety.safety_training' => ['nullable', 'string', $in('safety_training', 'safety.safety_training')],
            'safety.medical_fitness' => ['nullable', 'string', $in('medical_fitness', 'safety.medical_fitness')],
            'safety.incident_history' => ['nullable', 'string', 'max:255'],
            'performance_career' => ['nullable', 'array:kpi,career_plan'],
            'performance_career.kpi' => ['nullable', 'string', 'max:2000'],
            'performance_career.career_plan' => ['nullable', 'string', 'max:2000'],
            'assets_access' => ['nullable', 'array:laptop,mobile,sim,uniform,toolkit,access_card,email_access,erp_access'],
            'assets_access.uniform' => ['nullable', 'string', $in('issue_statuses', 'assets_access.uniform')],
            'assets_access.toolkit' => ['nullable', 'string', $in('issue_statuses', 'assets_access.toolkit')],
            'assets_access.email_access' => ['nullable', 'string', $in('access_statuses', 'assets_access.email_access')],
            'assets_access.erp_access' => ['nullable', 'string', $in('access_statuses', 'assets_access.erp_access')],
            'engagement_wellbeing' => ['nullable', 'array:notes'],
            'engagement_wellbeing.notes' => ['nullable', 'string', 'max:2000'],
            'exit_separation' => ['nullable', 'array:resignation_date,last_working_date,notes'],
            'exit_separation.resignation_date' => ['nullable', 'date', 'after_or_equal:date_of_joining'],
            'exit_separation.last_working_date' => ['nullable', 'date', 'after_or_equal:exit_separation.resignation_date', 'after_or_equal:date_of_joining'],
            'exit_separation.notes' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:8192'],
            ...IndiaLocations::optionalAddressRules('current_address.'),
            ...IndiaLocations::optionalAddressRules('permanent_address.'),
            ...IndiaLocations::optionalAddressRules('company_accommodation_address.'),
        ];
        foreach (['current_address', 'permanent_address', 'company_accommodation_address'] as $address) {
            $rules["{$address}.line1"] = ['nullable', 'string', 'max:255'];
            $rules["{$address}.city"] = ['nullable', 'string', 'max:120'];
            $rules["{$address}.pin"] = ['nullable', 'string', 'regex:/^[1-9][0-9]{5}$/'];
        }
        foreach (['laptop', 'mobile', 'sim', 'access_card'] as $asset) {
            $rules["assets_access.{$asset}"] = ['nullable', 'string', 'max:120'];
        }

        return $request->validate($rules, [
            'first_name.regex' => 'The first name can only contain letters, spaces, dots, hyphens and apostrophes.',
            'middle_name.regex' => 'The middle name can only contain letters, spaces, dots, hyphens and apostrophes.',
            'last_name.regex' => 'The last name can only contain letters, spaces, dots, hyphens and apostrophes.',
            'emergency_family.emergency_name.regex' => 'The emergency contact name can only contain letters, spaces, dots, hyphens and apostrophes.',
            'date_of_birth.before_or_equal' => 'Employees must be at least 18 years old. Check the date of birth.',
            'date_of_birth.after_or_equal' => 'The date of birth cannot be more than 80 years ago.',
            'date_of_joining.after' => 'The joining date must be after the date of birth.',
            'date_of_joining.before_or_equal' => 'The joining date cannot be more than a year from today.',
            'anniversary_date.after' => 'The anniversary must be after the date of birth.',
            'identity_documents.pan.regex' => 'PAN must look like ABCPD1234E (5 letters, 4 digits, 1 letter; the 4th letter is the holder type).',
            'identity_documents.passport.regex' => 'Passport number must be 1 letter followed by 7 digits (e.g. K1234567).',
            'identity_documents.driving_licence.regex' => 'Driving licence must look like MH12 20110012345 (state, RTO, year, serial).',
            'identity_documents.voter_id.regex' => 'Voter ID must be 3 letters followed by 7 digits (e.g. ABC1234567).',
            'verification.uan.regex' => 'UAN must be exactly 12 digits.',
            'verification.esic.regex' => 'ESIC number must be 10 or 17 digits.',
            'verification.police_verification_date.required_if' => 'Enter the date the police verification was cleared.',
            'verification.background_check_date.required_if' => 'Enter the date the background verification was cleared.',
            'bank_details.account_number.regex' => 'Account number must be 9 to 18 digits.',
            'bank_details.ifsc.regex' => 'IFSC must be 11 characters: 4 letters, a zero, then 6 letters or digits (e.g. HDFC0001234).',
            'emergency_family.mobile.different' => 'Emergency mobile must be a different number from the employee mobile.',
            'personal_email.different' => 'Personal email must be different from the work email.',
            'reporting_manager_id.not_in' => 'An employee cannot report to themselves.',
            'functional_manager_id.not_in' => 'An employee cannot be their own functional manager.',
            'hr_manager_id.not_in' => 'An employee cannot be their own HR manager.',
            '*.pin.regex' => 'PIN code must be 6 digits and cannot start with 0.',
            'exit_separation.resignation_date.after_or_equal' => 'The resignation date cannot be before the joining date.',
            'exit_separation.last_working_date.after_or_equal' => 'The last working date cannot be before the resignation or joining date.',
        ], [
            'phone' => 'mobile',
            'work_email' => 'work email',
            'identity_documents.aadhaar' => 'Aadhaar number',
            'emergency_family.emergency_name' => 'emergency contact name',
            'emergency_family.relation' => 'emergency contact relation',
            'emergency_family.mobile' => 'emergency mobile',
            'bank_details.bank_name' => 'bank name',
            'bank_details.account_number' => 'account number',
            'bank_details.ifsc' => 'IFSC',
            'benefits_allowances.loan_balance' => 'loan balance',
            'education_skills.experience_years' => 'experience',
            'premises' => 'assigned premises',
            'user_status' => 'status',
        ]);
    }

    /**
     * Strips the separators people type into ID, bank and PIN numbers and upper-cases codes,
     * matching what the browser does while typing, so pasted values validate the same way.
     */
    private function normaliseEmployeeInput(Request $request): void
    {
        $digits = fn (mixed $value): mixed => is_string($value) ? preg_replace('/\D+/', '', $value) : $value;
        $code = fn (mixed $value): mixed => is_string($value) ? strtoupper(preg_replace('/[\s-]+/', '', $value)) : $value;
        $paths = [
            'identity_documents.aadhaar' => $digits, 'identity_documents.pan' => $code, 'identity_documents.passport' => $code,
            'identity_documents.driving_licence' => $code, 'identity_documents.voter_id' => $code,
            'verification.uan' => $digits, 'verification.esic' => $digits,
            'bank_details.account_number' => $digits, 'bank_details.ifsc' => $code,
            'current_address.pin' => $digits, 'permanent_address.pin' => $digits, 'company_accommodation_address.pin' => $digits,
            'salary_currency' => $code, 'employee_code' => $code,
        ];
        $input = $request->input();
        foreach ($paths as $path => $normalise) {
            if (is_string(data_get($input, $path))) {
                data_set($input, $path, $normalise(data_get($input, $path)));
            }
        }
        foreach (['work_email', 'personal_email'] as $email) {
            if (is_string($input[$email] ?? null)) {
                $input[$email] = strtolower(trim($input[$email]));
            }
        }
        $request->replace($input);
    }

    /** @return array<string, mixed> */
    private function profilePayload(Request $request, array $validated, ?EmployeeProfile $profile = null): array
    {
        $documents = $profile?->documents ?? [];
        foreach ($request->file('documents', []) as $document) {
            $documents[] = ['name' => $document->getClientOriginalName(), 'path' => $document->store('employees/documents', 'public')];
        }
        $gross = (float) ($validated['gross_monthly_salary'] ?? 0);
        if ($gross <= 0) {
            $gross = collect(['basic_salary', 'hra', 'transport_allowance', 'fuel_allowance', 'other_allowance'])
                ->sum(fn (string $key): float => (float) ($validated[$key] ?? 0));
        }

        return [
            'salutation' => $validated['salutation'] ?? null,
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'local_script_name' => $validated['local_script_name'] ?? null,
            'profile_picture_path' => $request->file('profile_picture')?->store('employees/profile', 'public') ?? $profile?->profile_picture_path,
            'gender' => $validated['gender'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'marital_status' => $validated['marital_status'] ?? null,
            'anniversary_date' => $validated['anniversary_date'] ?? null,
            'blood_group' => $validated['blood_group'] ?? null,
            'nationality' => ($validated['nationality'] ?? null) ?: 'Indian',
            'religion' => $validated['religion'] ?? null,
            'languages' => $this->knownLanguages($validated),
            'identity_documents' => $validated['identity_documents'] ?? [],
            'personal_email' => $validated['personal_email'] ?? null,
            'current_address' => IndiaLocations::withCountry($validated['current_address'] ?? null),
            'permanent_address' => IndiaLocations::withCountry($validated['permanent_address'] ?? null),
            'company_accommodation_address' => IndiaLocations::withCountry($validated['company_accommodation_address'] ?? null),
            'emergency_family' => $validated['emergency_family'] ?? [],
            'date_of_joining' => $validated['date_of_joining'] ?? null,
            'employment_type' => $validated['employment_type'] ?? null,
            'employee_category' => $validated['employee_category'] ?? null,
            'job_grade' => $validated['job_grade'] ?? null,
            'reporting_manager_id' => $validated['reporting_manager_id'] ?? null,
            'functional_manager_id' => $validated['functional_manager_id'] ?? null,
            'hr_manager_id' => $validated['hr_manager_id'] ?? null,
            'employment_details' => $validated['employment_details'] ?? [],
            'verification' => $validated['verification'] ?? [],
            'salary_currency' => $validated['salary_currency'],
            'pay_frequency' => $validated['pay_frequency'],
            'pay_group' => $validated['pay_group'] ?? null,
            'basic_salary' => $validated['basic_salary'] ?? 0,
            'hra' => $validated['hra'] ?? 0,
            'transport_allowance' => $validated['transport_allowance'] ?? 0,
            'fuel_allowance' => $validated['fuel_allowance'] ?? 0,
            'other_allowance' => $validated['other_allowance'] ?? 0,
            'gross_monthly_salary' => $gross,
            'compensation' => $validated['compensation'] ?? [],
            'bank_details' => $validated['bank_details'] ?? [],
            'benefits_allowances' => $validated['benefits_allowances'] ?? [],
            'education_skills' => $validated['education_skills'] ?? [],
            'safety' => $validated['safety'] ?? [],
            'performance_career' => $validated['performance_career'] ?? [],
            'assets_access' => $validated['assets_access'] ?? [],
            'engagement_wellbeing' => $validated['engagement_wellbeing'] ?? [],
            'exit_separation' => $validated['exit_separation'] ?? [],
            'documents' => $documents,
            'remarks' => $validated['remarks'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function formData(?User $employee = null): array
    {
        return [
            'employee' => $employee,
            'profile' => $employee?->employeeProfile,
            'roles' => Role::query()->orderBy('name')->get(),
            'premises' => Premises::query()->where('active', true)->orderBy('name')->get(),
            'managers' => User::query()->where('status', 'active')->when($employee, fn ($query) => $query->where('id', '!=', $employee->id))->orderBy('name')->get(['id', 'name', 'employee_code']),
            'languages' => EmployeeMasterOptions::languages(),
            'verificationStatuses' => EmployeeMasterOptions::verificationStatuses(),
            'departments' => EmployeeMasterOptions::departments(),
            'options' => fn (string $list): array => EmployeeMasterOptions::options($list),
        ];
    }

    private function fullName(array $validated): string
    {
        return collect([$validated['first_name'], $validated['middle_name'] ?? null, $validated['last_name'] ?? null])->filter()->join(' ');
    }

    /**
     * Known languages, always including the preferred app language.
     *
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function knownLanguages(array $validated): array
    {
        $languages = array_values(array_unique(array_filter($validated['languages'] ?? [])));
        $preferred = $this->preferredLanguage($validated);

        if ($preferred && ! in_array($preferred, $languages, true)) {
            $languages[] = $preferred;
        }

        return $languages;
    }

    /** @param array<string, mixed> $validated */
    private function preferredLanguage(array $validated): ?string
    {
        $preferred = $validated['preferred_language'] ?? null;

        if ($preferred) {
            return $preferred;
        }

        $languages = array_values(array_filter($validated['languages'] ?? []));

        return $languages[0] ?? null;
    }

    private function nextEmployeeCode(): string
    {
        return 'EMP'.str_pad((string) ((User::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function syncLeaveBalances(User $user): void
    {
        LeaveType::query()->where('active', true)->each(function (LeaveType $leaveType) use ($user): void {
            LeaveBalance::firstOrCreate(
                ['user_id' => $user->id, 'leave_type_id' => $leaveType->id, 'year' => now()->year],
                ['allocated' => $leaveType->annual_quota],
            );
        });
    }
}
