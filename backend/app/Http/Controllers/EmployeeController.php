<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Premises;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
                'probation' => User::whereHas('employeeProfile', fn ($query) => $query->where('employment_details->employment_status', 'probation'))->count(),
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
                'employee_code' => $validated['employee_code'],
                'department' => $validated['department'] ?? null,
                'designation' => $validated['designation'] ?? null,
                'status' => $validated['user_status'],
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
        return $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'local_script_name' => ['nullable', 'string', 'max:255'],
            'salutation' => ['nullable', Rule::in(['Mr', 'Mrs', 'Miss', 'Dr'])],
            'gender' => ['nullable', Rule::in(['male', 'female', 'transgender', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', Rule::in(['married', 'unmarried', 'other'])],
            'anniversary_date' => ['nullable', 'date'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'religion' => ['nullable', 'string', 'max:80'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:50'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'work_email' => ['required', 'email', Rule::unique('users', 'email')->ignore($employee)],
            'personal_email' => ['nullable', 'email'],
            'phone' => ['required', 'string', 'max:20'],
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('users', 'employee_code')->ignore($employee)],
            'department' => ['required', 'string', 'max:100'],
            'designation' => ['required', 'string', 'max:100'],
            'role' => ['required', 'exists:roles,name'],
            'user_status' => ['required', Rule::in(['active', 'inactive', 'on_leave', 'suspended'])],
            'premises' => ['required', 'array', 'min:1'],
            'premises.*' => ['integer', 'exists:premises,id'],
            'date_of_joining' => ['nullable', 'date'],
            'employment_type' => ['nullable', 'string', 'max:50'],
            'employee_category' => ['nullable', 'string', 'max:80'],
            'job_grade' => ['nullable', 'string', 'max:30'],
            'reporting_manager_id' => ['nullable', 'exists:users,id'],
            'functional_manager_id' => ['nullable', 'exists:users,id'],
            'hr_manager_id' => ['nullable', 'exists:users,id'],
            'salary_currency' => ['required', 'string', 'max:10'],
            'pay_frequency' => ['required', Rule::in(['monthly', 'weekly', 'daily'])],
            'pay_group' => ['nullable', 'string', 'max:50'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'hra' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'fuel_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'gross_monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'identity_documents' => ['nullable', 'array'],
            'current_address' => ['nullable', 'array'],
            'permanent_address' => ['nullable', 'array'],
            'company_accommodation_address' => ['nullable', 'array'],
            'emergency_family' => ['nullable', 'array'],
            'employment_details' => ['nullable', 'array'],
            'verification' => ['nullable', 'array'],
            'compensation' => ['nullable', 'array'],
            'bank_details' => ['nullable', 'array'],
            'benefits_allowances' => ['nullable', 'array'],
            'education_skills' => ['nullable', 'array'],
            'safety' => ['nullable', 'array'],
            'performance_career' => ['nullable', 'array'],
            'assets_access' => ['nullable', 'array'],
            'engagement_wellbeing' => ['nullable', 'array'],
            'exit_separation' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:8192'],
        ]);
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
            'languages' => $validated['languages'] ?? [],
            'identity_documents' => $validated['identity_documents'] ?? [],
            'personal_email' => $validated['personal_email'] ?? null,
            'current_address' => $validated['current_address'] ?? [],
            'permanent_address' => $validated['permanent_address'] ?? [],
            'company_accommodation_address' => $validated['company_accommodation_address'] ?? [],
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
        ];
    }

    private function fullName(array $validated): string
    {
        return collect([$validated['first_name'], $validated['middle_name'] ?? null, $validated['last_name'] ?? null])->filter()->join(' ');
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
