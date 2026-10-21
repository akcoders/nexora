<x-app-layout>
    <x-slot name="title">{{ $employee ? 'Edit Employee' : 'New Employee' }}</x-slot><x-slot name="pageTitle">{{ $employee ? 'Edit employee' : 'Create employee' }}</x-slot><x-slot name="breadcrumb">HR / Employees / {{ $employee ? 'Edit' : 'New' }}</x-slot>
    @php
        $v = fn($key,$default='') => old($key, data_get($profile,$key,$default));
        $u = fn($key,$default='') => old($key, data_get($employee,$key,$default));
        $role = old('role', $employee?->getRoleNames()->first());
        $selectedPremises = old('premises', $employee?->premises?->pluck('id')->all() ?? []);
        $sections = [
            ['Identity','user-round','Who they are'],
            ['Contact','map-pin-house','Address & family'],
            ['Employment','briefcase-business','Role & access'],
            ['Verification','shield-check','IDs & checks'],
            ['Compensation','indian-rupee','Salary & bank'],
            ['Benefits','heart-pulse','Benefits & wellbeing'],
            ['Skills & Safety','graduation-cap','Education & safety'],
            ['Assets & Exit','laptop','Assets & separation'],
        ];
        $langs = old('languages', $profile?->languages ?? []);
        $preferredLanguage = old('preferred_language', $employee?->preferred_language ?? ($langs[0] ?? ''));
        $identityDocs = ['aadhaar'=>['Aadhaar number','aadhaar','1234 5678 9012'],'pan'=>['PAN','pan','ABCDE1234F'],'passport'=>['Passport number','passport','A1234567'],'driving_licence'=>['Driving licence','dl','MH12 20200012345'],'voter_id'=>['Voter ID','voter','ABC1234567']];
        $verifications = [
            'police_verification' => ['Police verification','siren','Local police / character certificate','police_verification_reference','Certificate / reference no.'],
            'background_check' => ['Background verification','search-check','Employment, address & education check','background_check_agency','Verifying agency'],
        ];
        $ver = fn($key,$default='') => old('verification.'.$key, data_get($profile,'verification.'.$key,$default));
        $existingDocs = $profile?->documents ?? [];
        // Renders <option>s; a saved value that is no longer offered stays selectable so older records still save.
        $opt = function (array $choices, mixed $selected, ?string $blank = 'Select'): string {
            $selected = $selected === null ? '' : (string) $selected;
            $html = $blank !== null ? '<option value="">'.e($blank).'</option>' : '';
            foreach ($choices as $value => $label) {
                $html .= '<option value="'.e($value).'"'.((string) $value === $selected ? ' selected' : '').'>'.e($label).'</option>';
            }
            if ($selected !== '' && ! array_key_exists($selected, $choices)) {
                $html .= '<option value="'.e($selected).'" selected>'.e($selected).'</option>';
            }

            return $html;
        };
        $managerChoices = collect($managers)->mapWithKeys(fn ($x) => [$x->id => trim($x->name.' · '.$x->employee_code, ' ·')])->all();
        $department = $u('department');
        $designationChoices = collect($departments[$department] ?? array_merge(...array_values($departments)))->unique()->mapWithKeys(fn ($x) => [$x => $x])->all();
    @endphp
    @if($errors->any())<div class="alert alert-danger nx-error-summary"><strong class="d-flex align-items-center gap-2"><i data-lucide="circle-alert" style="width:17px"></i>Please fix the highlighted fields.</strong><div class="small mt-1">The first step with an error opens automatically. {{ $errors->first() }}</div></div>@endif
    <form id="employeeWizard" class="nx-employee-form" method="POST" enctype="multipart/form-data" action="{{ $employee ? route('employees.update',$employee) : route('employees.store') }}" novalidate>@csrf @if($employee)@method('PUT')@endif
        <section class="nx-form-hero nx-employee-hero mb-4">
            <div class="nx-form-hero-icon"><i data-lucide="users-round"></i></div>
            <div class="min-w-0"><span class="nx-form-hero-kicker">PEOPLE & CULTURE</span><h2>{{ $employee ? 'Every detail helps us support '.$employee->name : 'Let\'s welcome someone wonderful to the team' }}</h2><p>Take it one step at a time. Only the fields marked * are required - everything else can be completed later.</p></div>
            <span class="nx-form-hero-badge"><i data-lucide="heart-handshake"></i> People first</span>
        </section>
        <section class="nx-card p-3 p-lg-4 mb-4">
            <div class="nx-stepper">@foreach($sections as $i=>[$label,$icon,$hint])<button class="nx-step {{ $i===0?'active':'' }}" type="button" data-step="{{ $i+1 }}" title="{{ $hint }}"><span class="nx-step-number"><i data-lucide="{{ $icon }}"></i><span>{{ $i+1 }}</span></span><span>{{ $label }}</span></button>@endforeach</div>
            <div class="nx-employee-progress" aria-hidden="true"><span id="employeeProgress" style="width:12.5%"></span></div>
        </section>
        <section class="nx-card p-3 p-lg-4">
            {{-- 1. Identity --}}
            <div class="nx-step-pane active" data-pane="1">
                <div class="nx-step-help"><div><h2 class="nx-section-title mb-1">Core identity</h2><p class="small text-secondary mb-0">Personal identity, photo and the languages this person works in.</p></div><span class="nx-step-counter">Step 1 of 8</span></div>
                <div class="row g-3">
                    <div class="col-md-2"><label class="form-label">Salutation</label><select class="form-select" name="salutation">{!! $opt($options('salutations'), $v('salutation'), '-') !!}</select></div>
                    <div class="col-md-4"><label class="form-label">First name *</label><input class="form-control" name="first_name" value="{{ old('first_name',$profile?->first_name) }}" required data-v="person_name|minlength:2" maxlength="120" autofocus></div>
                    <div class="col-md-3"><label class="form-label">Middle name</label><input class="form-control" name="middle_name" value="{{ $v('middle_name') }}" data-v="person_name" maxlength="120"></div>
                    <div class="col-md-3"><label class="form-label">Last name</label><input class="form-control" name="last_name" value="{{ $v('last_name') }}" data-v="person_name" maxlength="120"></div>
                    <div class="col-md-4"><label class="form-label">Name in local script</label><input class="form-control" name="local_script_name" value="{{ $v('local_script_name') }}" placeholder="e.g. नेहा पाटील" maxlength="255"></div>
                    <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender">{!! $opt($options('genders'), $v('gender')) !!}</select></div>
                    <div class="col-md-3"><label class="form-label">Date of birth</label><input type="date" class="form-control" name="date_of_birth" value="{{ $v('date_of_birth') }}" data-v="before:today|age_min:18|age_max:80" data-msg-age-min="Employees must be at least 18 years old. Check the date of birth."></div>
                    <div class="col-md-2"><label class="form-label">Blood group</label><select class="form-select" name="blood_group">{!! $opt($options('blood_groups'), $v('blood_group'), '-') !!}</select></div>
                    <div class="col-md-3"><label class="form-label">Marital status</label><select class="form-select" name="marital_status" id="maritalStatus">{!! $opt($options('marital_statuses'), $v('marital_status')) !!}</select></div>
                    <div class="col-md-3"><label class="form-label">Anniversary</label><input type="date" class="form-control" name="anniversary_date" id="anniversaryDate" value="{{ $v('anniversary_date') }}" data-v="not_future|after:date_of_birth"><div class="form-text">Only for married employees.</div></div>
                    <div class="col-md-3"><label class="form-label">Nationality</label><select class="form-select" name="nationality">{!! $opt($options('nationalities'), $v('nationality','Indian'), null) !!}</select></div>
                    <div class="col-md-3"><label class="form-label">Religion</label><select class="form-select" name="religion">{!! $opt($options('religions'), $v('religion')) !!}</select></div>
                    <div class="col-12">
                        <div class="nx-field-group tone-violet">
                            <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="languages"></i></span><div><strong>Languages known & app language</strong><small>The app shows in the preferred language. When several are selected, the person can switch from the language menu at the top of the app.</small></div></div>
                            <div class="nx-language-picker mb-3" id="languagePicker">@foreach($languages as $name=>$code)<label class="nx-choice"><input type="checkbox" name="languages[]" value="{{ $name }}" @checked(in_array($name,$langs))><span><span class="nx-lang-code notranslate">{{ $code }}</span>{{ $name }}</span></label>@endforeach</div>
                            <div class="nx-preferred-language"><i data-lucide="sparkles"></i><div class="flex-grow-1"><label class="form-label mb-1" for="preferredLanguage">Preferred app language</label><select class="form-select" id="preferredLanguage" name="preferred_language"><option value="">Same as first selected language</option>@foreach($languages as $name=>$code)<option value="{{ $name }}" @selected($preferredLanguage===$name)>{{ $name }}</option>@endforeach</select></div></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Profile picture</label>
                        <div class="d-flex align-items-center gap-3">
                            @if($profile?->profile_picture_path)<img class="rounded-4 object-fit-cover flex-shrink-0" width="56" height="56" src="{{ asset('storage/'.$profile->profile_picture_path) }}" alt="">@endif
                            <input type="file" class="form-control" name="profile_picture" accept="image/png,image/jpeg,image/webp" data-max-size="3072">
                        </div>
                        <div class="form-text">PNG, JPG or WebP up to 3 MB.</div>
                    </div>
                </div>
            </div>

            {{-- 2. Contact --}}
            <div class="nx-step-pane" data-pane="2">
                <div class="nx-step-help"><div><h2 class="nx-section-title mb-1">Contact, address & family</h2><p class="small text-secondary mb-0">How to reach the employee and who to call in an emergency.</p></div><span class="nx-step-counter">Step 2 of 8</span></div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Work email *</label><div class="nx-input-icon"><i data-lucide="mail"></i><input type="email" class="form-control" name="work_email" value="{{ old('work_email',$employee?->email) }}" required></div><div class="form-text">Used for login to the web app and the technician app.</div></div>
                    <div class="col-md-4"><label class="form-label">Personal email</label><div class="nx-input-icon"><i data-lucide="at-sign"></i><input type="email" class="form-control" name="personal_email" value="{{ $v('personal_email') }}" data-v="different:work_email"></div></div>
                    <div class="col-md-4"><label class="form-label">Mobile *</label><div class="nx-input-icon"><i data-lucide="smartphone"></i><input class="form-control" name="phone" value="{{ $u('phone') }}" required data-v="mobile" inputmode="tel"></div></div>
                    @foreach(['current_address'=>['Current address','home','tone-sky'],'permanent_address'=>['Permanent address','map-pinned','tone-indigo'],'company_accommodation_address'=>['Company accommodation','building','tone-teal']] as $key=>[$label,$icon,$tone])
                    <div class="col-12"><div class="nx-field-group {{ $tone }}">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="{{ $icon }}"></i></span><div><strong>{{ $label }}</strong><small>{{ $key==='company_accommodation_address' ? 'Only if the company provides accommodation.' : 'Street, state, district and PIN code.' }}</small></div>@if($key==='permanent_address')<button type="button" class="btn btn-sm btn-light border ms-auto" id="copyCurrentAddress"><i data-lucide="copy" style="width:14px"></i>Same as current</button>@endif</div>
                        <div class="row g-2">
                            <div class="col-md-6"><input class="form-control" name="{{ $key }}[line1]" value="{{ old($key.'.line1',data_get($profile,$key.'.line1')) }}" placeholder="Address line" maxlength="255"></div>
                            @foreach(['state'=>'State','district'=>'District'] as $part=>$partLabel)@php $selected=old($key.'.'.$part,data_get($profile,$key.'.'.$part)); @endphp
                            <div class="col-md-3"><select class="form-select" name="{{ $key }}[{{ $part }}]" data-india-{{ $part }} data-label="{{ $label }} {{ strtolower($partLabel) }}"><option value="">Select {{ strtolower($partLabel) }}</option>@if(filled($selected))<option value="{{ $selected }}" selected>{{ $selected }}</option>@endif</select></div>
                            @endforeach
                            <div class="col-md-4"><input class="form-control" name="{{ $key }}[city]" value="{{ old($key.'.city',data_get($profile,$key.'.city')) }}" placeholder="City / town" maxlength="120"></div>
                            <div class="col-md-4"><input class="form-control" name="{{ $key }}[pin]" value="{{ old($key.'.pin',data_get($profile,$key.'.pin')) }}" placeholder="PIN code" data-v="pincode" inputmode="numeric"></div>
                            <div class="col-md-4"><input class="form-control nx-country-fixed" name="{{ $key }}[country]" value="India" readonly aria-label="Country"></div>
                        </div>
                    </div></div>
                    @endforeach
                    <div class="col-12"><div class="nx-field-group tone-rose">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="phone-call"></i></span><div><strong>Emergency contact</strong><small>Family member or friend we can reach in an emergency.</small></div></div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Contact name</label><input class="form-control" name="emergency_family[emergency_name]" value="{{ old('emergency_family.emergency_name',data_get($profile,'emergency_family.emergency_name')) }}" data-v="person_name|required_with:mobile" maxlength="120"></div>
                            <div class="col-md-4"><label class="form-label">Relation</label><select class="form-select" name="emergency_family[relation]" data-v="required_with:emergency_name">{!! $opt($options('relations'), old('emergency_family.relation',data_get($profile,'emergency_family.relation'))) !!}</select></div>
                            <div class="col-md-4"><label class="form-label">Emergency mobile</label><input class="form-control" name="emergency_family[mobile]" value="{{ old('emergency_family.mobile',data_get($profile,'emergency_family.mobile')) }}" data-v="mobile|different:phone|required_with:emergency_name" data-msg-different="Emergency mobile must be a different number from the employee mobile." inputmode="tel"></div>
                        </div>
                    </div></div>
                </div>
            </div>

            {{-- 3. Employment --}}
            <div class="nx-step-pane" data-pane="3">
                <div class="nx-step-help"><div><h2 class="nx-section-title mb-1">Employment & user access</h2><p class="small text-secondary mb-0">Where they sit in the organisation and what they can open in the apps.</p></div><span class="nx-step-counter">Step 3 of 8</span></div>
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Employee code</label><input class="form-control" name="employee_code" value="{{ $u('employee_code') }}" placeholder="Auto generated" data-v="alpha_dash" maxlength="30" data-upper></div>
                    <div class="col-md-3"><label class="form-label">Joining date</label><input type="date" class="form-control" name="date_of_joining" value="{{ $v('date_of_joining') }}" data-v="after:date_of_birth"></div>
                    <div class="col-md-3"><label class="form-label">Employment type</label><select class="form-select" name="employment_type">{!! $opt($options('employment_types'), $v('employment_type','permanent'), null) !!}</select></div>
                    <div class="col-md-3"><label class="form-label">Category</label><select class="form-select" name="employee_category">{!! $opt($options('employee_categories'), $v('employee_category')) !!}</select></div>
                    <div class="col-md-4"><label class="form-label">Department *</label><select class="form-select" name="department" id="employeeDepartment" required data-searchable data-placeholder="Select department">{!! $opt(array_combine(array_keys($departments), array_keys($departments)), $department, 'Select department') !!}</select></div>
                    <div class="col-md-4"><label class="form-label">Designation *</label><select class="form-select" name="designation" id="employeeDesignation" required data-searchable data-placeholder="Select designation" data-designations='@json($departments)'>{!! $opt($designationChoices, $u('designation'), 'Select designation') !!}</select><div class="form-text">Filtered by the selected department.</div></div>
                    <div class="col-md-2"><label class="form-label">Job grade</label><select class="form-select" name="job_grade">{!! $opt($options('job_grades'), $v('job_grade'), '-') !!}</select></div>
                    <div class="col-md-2"><label class="form-label">Status *</label><select class="form-select nx-status-select" name="user_status" data-tone-map='{"active":"success","inactive":"secondary","on_leave":"warning","suspended":"danger"}'>{!! $opt($options('user_statuses'), $u('status','active'), null) !!}</select></div>
                    <div class="col-12"><div class="nx-field-group tone-indigo">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="key-round"></i></span><div><strong>App access</strong><small>Role decides the permissions; premises decide where attendance can be marked.</small></div></div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Role *</label><select class="form-select" name="role" required><option value="">Select role</option>@foreach($roles as $x)<option value="{{ $x->name }}" @selected($role===$x->name)>{{ $x->name }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Reporting manager</label><select class="form-select" name="reporting_manager_id" data-searchable data-placeholder="None">{!! $opt($managerChoices, $v('reporting_manager_id'), 'None') !!}</select></div>
                            <div class="col-md-4"><label class="form-label">Assigned premises *</label><select class="form-select" name="premises[]" multiple size="4" required>@foreach($premises as $x)<option value="{{ $x->id }}" @selected(in_array($x->id,$selectedPremises))>{{ $x->name }}</option>@endforeach</select><div class="form-text">Hold Ctrl / Cmd to select more than one.</div></div>
                            <div class="col-md-4"><label class="form-label">Functional manager</label><select class="form-select" name="functional_manager_id" data-searchable data-placeholder="None">{!! $opt($managerChoices, $v('functional_manager_id'), 'None') !!}</select></div>
                            <div class="col-md-4"><label class="form-label">HR manager</label><select class="form-select" name="hr_manager_id" data-searchable data-placeholder="None">{!! $opt($managerChoices, $v('hr_manager_id'), 'None') !!}</select></div>
                        </div>
                    </div></div>
                </div>
            </div>

            {{-- 4. Verification --}}
            <div class="nx-step-pane" data-pane="4">
                <div class="nx-step-help"><div><h2 class="nx-section-title mb-1">Identity & employee verification</h2><p class="small text-secondary mb-0">Government IDs, statutory numbers and the status of police and background checks.</p></div><span class="nx-step-counter">Step 4 of 8</span></div>
                <div class="row g-3">
                    <div class="col-12"><div class="nx-field-group tone-sky">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="id-card"></i></span><div><strong>Identity documents</strong><small>Numbers are validated for format. Leave blank if not available.</small></div></div>
                        <div class="row g-3">
                            @foreach($identityDocs as $key=>[$label,$rule,$placeholder])
                            <div class="col-md-4"><label class="form-label">{{ $label }}</label><input class="form-control notranslate" name="identity_documents[{{ $key }}]" value="{{ old('identity_documents.'.$key,data_get($profile,'identity_documents.'.$key)) }}" data-v="{{ $rule }}" placeholder="{{ $placeholder }}" maxlength="40"></div>
                            @endforeach
                            <div class="col-md-4"><label class="form-label">UAN (PF)</label><input class="form-control notranslate" name="verification[uan]" value="{{ $ver('uan') }}" data-v="uan" placeholder="12 digits" maxlength="30"></div>
                            <div class="col-md-4"><label class="form-label">ESIC number</label><input class="form-control notranslate" name="verification[esic]" value="{{ $ver('esic') }}" data-v="esic" placeholder="10 or 17 digits" maxlength="30"></div>
                        </div>
                    </div></div>
                    @foreach($verifications as $key=>[$label,$icon,$hint,$refKey,$refLabel])
                    @php $status = $ver($key) ?: 'not_started'; $tone = \App\Services\EmployeeMasterOptions::verificationTone($status); @endphp
                    <div class="col-lg-6"><div class="nx-field-group nx-verify-card h-100 {{ $key==='police_verification' ? 'tone-amber' : 'tone-emerald' }}">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="{{ $icon }}"></i></span><div><strong>{{ $label }}</strong><small>{{ $hint }}</small></div><span class="nx-verify-status tone-{{ $tone }} ms-auto" data-status-badge="{{ $key }}">{{ \App\Services\EmployeeMasterOptions::verificationLabel($status) }}</span></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Status *</label><select class="form-select nx-status-select" name="verification[{{ $key }}]" data-status-for="{{ $key }}" data-tone="{{ $tone }}" data-tone-map='@json(collect($verificationStatuses)->map(fn($s)=>$s['tone']))' data-label-map='@json(collect($verificationStatuses)->map(fn($s)=>$s['label']))'>@foreach($verificationStatuses as $value=>$meta)<option value="{{ $value }}" @selected($status===$value)>{{ $meta['label'] }}</option>@endforeach</select></div>
                            <div class="col-md-6"><label class="form-label">Completed on</label><input type="date" class="form-control" name="verification[{{ $key }}_date]" value="{{ $ver($key.'_date') }}" data-v="not_future|required_if:{{ $key }},verified" data-msg-required-if="Enter the date this check was cleared."></div>
                            <div class="col-md-6"><label class="form-label">{{ $refLabel }}</label><input class="form-control" name="verification[{{ $refKey }}]" value="{{ $ver($refKey) }}" maxlength="120"></div>
                            <div class="col-md-6"><label class="form-label">Remarks</label><input class="form-control" name="verification[{{ $key }}_remarks]" value="{{ $ver($key.'_remarks') }}" maxlength="1000" placeholder="Findings, follow-up, renewal date..."></div>
                        </div>
                    </div></div>
                    @endforeach
                    <div class="col-12">
                        <label class="form-label">Supporting documents</label>
                        <div class="nx-dropzone" id="documentDropzone"><i data-lucide="cloud-upload"></i><span>Drop files here or click to browse</span><small>PDF, images or Word files · up to 8 MB each · 20 files max</small><input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" data-max-size="8192" data-max-files="20"></div>
                        <div class="small text-secondary mt-2" id="documentList"></div>
                        @if(count($existingDocs))<div class="d-flex flex-wrap gap-2 mt-2">@foreach($existingDocs as $doc)<a class="nx-doc-chip" target="_blank" rel="noopener" href="{{ asset('storage/'.($doc['path'] ?? '')) }}"><i data-lucide="file-text"></i><span>{{ $doc['name'] ?? 'Document' }}</span></a>@endforeach</div>@endif
                    </div>
                </div>
            </div>

            {{-- 5. Compensation --}}
            <div class="nx-step-pane" data-pane="5">
                <div class="nx-step-help"><div><h2 class="nx-section-title mb-1">Compensation & payment</h2><p class="small text-secondary mb-0">Salary structure used by payroll, and the bank account for payouts.</p></div><span class="nx-step-counter">Step 5 of 8</span></div>
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Currency *</label><select class="form-select notranslate" name="salary_currency" required>{!! $opt($options('currencies'), $v('salary_currency','INR'), null) !!}</select></div>
                    <div class="col-md-3"><label class="form-label">Frequency *</label><select class="form-select" name="pay_frequency" required>{!! $opt($options('pay_frequencies'), $v('pay_frequency','monthly'), null) !!}</select></div>
                    <div class="col-md-3"><label class="form-label">Pay group</label><select class="form-select" name="pay_group">{!! $opt($options('pay_groups'), $v('pay_group')) !!}</select></div>
                    <div class="col-md-3"><label class="form-label">Gross monthly</label><input type="number" step=".01" class="form-control" name="gross_monthly_salary" id="grossMonthly" value="{{ $v('gross_monthly_salary',0) }}" min="0" max="10000000" data-v="gte:basic_salary|decimals:2"><div class="form-text">Leave 0 to auto-total the components.</div></div>
                    <div class="col-12"><div class="nx-field-group tone-emerald">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="coins"></i></span><div><strong>Salary components</strong><small>Monthly amounts in {{ $v('salary_currency','INR') }}.</small></div><span class="ms-auto small fw-bold text-success" id="componentTotal"></span></div>
                        <div class="row g-3">@foreach(['basic_salary'=>'Basic salary','hra'=>'HRA','transport_allowance'=>'Transport','fuel_allowance'=>'Fuel','other_allowance'=>'Other allowance'] as $key=>$label)
                            <div class="col-md-4 col-lg"><label class="form-label">{{ $label }}</label><input type="number" step=".01" min="0" max="10000000" class="form-control salary-component" name="{{ $key }}" value="{{ $v($key,0) }}" data-v="decimals:2"></div>@endforeach
                        </div>
                    </div></div>
                    <div class="col-12"><div class="nx-field-group tone-indigo">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="landmark"></i></span><div><strong>Bank account</strong><small>All three fields are required together.</small></div></div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Bank name</label><input class="form-control" name="bank_details[bank_name]" value="{{ old('bank_details.bank_name',data_get($profile,'bank_details.bank_name')) }}" data-v="required_with:account_number,ifsc" maxlength="120"></div>
                            <div class="col-md-4"><label class="form-label">Account number</label><input class="form-control notranslate" name="bank_details[account_number]" value="{{ old('bank_details.account_number',data_get($profile,'bank_details.account_number')) }}" data-v="account|required_with:ifsc,bank_name" inputmode="numeric"></div>
                            <div class="col-md-4"><label class="form-label">IFSC</label><input class="form-control notranslate" name="bank_details[ifsc]" value="{{ old('bank_details.ifsc',data_get($profile,'bank_details.ifsc')) }}" data-v="ifsc|required_with:account_number,bank_name" data-upper></div>
                        </div>
                    </div></div>
                </div>
            </div>

            {{-- 6. Benefits --}}
            <div class="nx-step-pane" data-pane="6">
                <div class="nx-step-help"><div><h2 class="nx-section-title mb-1">Benefits, allowance & wellbeing</h2><p class="small text-secondary mb-0">Statutory eligibility and the extras the company offers.</p></div><span class="nx-step-counter">Step 6 of 8</span></div>
                <div class="row g-3">
                    <div class="col-12"><div class="nx-field-group tone-amber">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="shield-plus"></i></span><div><strong>Statutory & insurance</strong><small>PF, ESIC and policies.</small></div></div>
                        <div class="row g-3">
                            @foreach(['pf_eligible'=>'PF eligible','esic_eligible'=>'ESIC eligible'] as $key=>$label)@php $val = old('benefits_allowances.'.$key,data_get($profile,'benefits_allowances.'.$key)); @endphp
                            <div class="col-md-3"><label class="form-label">{{ $label }}</label><select class="form-select" name="benefits_allowances[{{ $key }}]"><option value="">Not set</option><option value="yes" @selected(in_array(strtolower((string)$val),['yes','1','true']))>Yes</option><option value="no" @selected(in_array(strtolower((string)$val),['no','0','false']))>No</option></select></div>
                            @endforeach
                            <div class="col-md-3"><label class="form-label">Insurance policy</label><input class="form-control" name="benefits_allowances[insurance_policy]" value="{{ old('benefits_allowances.insurance_policy',data_get($profile,'benefits_allowances.insurance_policy')) }}" maxlength="120" placeholder="Policy no. / insurer"></div>
                            <div class="col-md-3"><label class="form-label">Gratuity eligible</label><select class="form-select" name="benefits_allowances[gratuity]">{!! $opt($options('yes_no'), old('benefits_allowances.gratuity',data_get($profile,'benefits_allowances.gratuity')), 'Not set') !!}</select></div>
                        </div>
                    </div></div>
                    <div class="col-12"><div class="nx-field-group tone-emerald">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="gift"></i></span><div><strong>Allowances & perks</strong><small>Loans, bonus and benefits in kind.</small></div></div>
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Loan balance</label><input type="number" step=".01" min="0" max="10000000" class="form-control" name="benefits_allowances[loan_balance]" value="{{ old('benefits_allowances.loan_balance',data_get($profile,'benefits_allowances.loan_balance')) }}" data-v="decimals:2" placeholder="0.00"></div>
                            @foreach(['bonus_plan'=>'Bonus plan','medical_benefit'=>'Medical benefit','meal_benefit'=>'Meal benefit'] as $key=>$label)
                            <div class="col-md-3"><label class="form-label">{{ $label }}</label><input class="form-control" name="benefits_allowances[{{ $key }}]" value="{{ old('benefits_allowances.'.$key,data_get($profile,'benefits_allowances.'.$key)) }}" maxlength="120"></div>
                            @endforeach
                        </div>
                    </div></div>
                    <div class="col-md-6"><label class="form-label">Wellbeing notes</label><textarea class="form-control" name="engagement_wellbeing[notes]" rows="3" maxlength="2000">{{ old('engagement_wellbeing.notes',data_get($profile,'engagement_wellbeing.notes')) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Employee remarks</label><textarea class="form-control" name="remarks" rows="3" maxlength="5000">{{ $v('remarks') }}</textarea></div>
                </div>
            </div>

            {{-- 7. Skills & Safety --}}
            <div class="nx-step-pane" data-pane="7">
                <div class="nx-step-help"><div><h2 class="nx-section-title mb-1">Education, skills, safety & performance</h2><p class="small text-secondary mb-0">Qualifications, trade skills, safety readiness and growth plan.</p></div><span class="nx-step-counter">Step 7 of 8</span></div>
                <div class="row g-3">
                    <div class="col-12"><div class="nx-field-group tone-sky">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="graduation-cap"></i></span><div><strong>Education & skills</strong></div></div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Highest qualification</label><select class="form-select" name="education_skills[highest_qualification]">{!! $opt($options('qualifications'), old('education_skills.highest_qualification',data_get($profile,'education_skills.highest_qualification'))) !!}</select></div>
                            @foreach(['specialization'=>'Specialization','technical_skills'=>'Technical skills','certifications'=>'Certifications','trade_licence'=>'Trade licence'] as $key=>$label)
                            <div class="col-md-4"><label class="form-label">{{ $label }}</label><input class="form-control" name="education_skills[{{ $key }}]" value="{{ old('education_skills.'.$key,data_get($profile,'education_skills.'.$key)) }}" maxlength="255"></div>
                            @endforeach
                            <div class="col-md-4"><label class="form-label">Experience (years)</label><input type="number" min="0" max="60" step=".5" class="form-control" name="education_skills[experience_years]" value="{{ old('education_skills.experience_years',data_get($profile,'education_skills.experience_years')) }}" data-v="step:0.5"></div>
                        </div>
                    </div></div>
                    <div class="col-12"><div class="nx-field-group tone-rose">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="hard-hat"></i></span><div><strong>Safety</strong><small>PPE, training and fitness for field work.</small></div></div>
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">PPE size</label><select class="form-select" name="safety[ppe_size]">{!! $opt($options('ppe_sizes'), old('safety.ppe_size',data_get($profile,'safety.ppe_size'))) !!}</select></div>
                            @foreach(['safety_training'=>'Safety training','medical_fitness'=>'Medical fitness'] as $key=>$label)
                            <div class="col-md-3"><label class="form-label">{{ $label }}</label><select class="form-select" name="safety[{{ $key }}]">{!! $opt($options($key), old('safety.'.$key,data_get($profile,'safety.'.$key))) !!}</select></div>
                            @endforeach
                            <div class="col-md-3"><label class="form-label">Incident history</label><input class="form-control" name="safety[incident_history]" value="{{ old('safety.incident_history',data_get($profile,'safety.incident_history')) }}" maxlength="255" placeholder="None / brief note"></div>
                        </div>
                    </div></div>
                    <div class="col-md-6"><label class="form-label">Goals / KPI</label><textarea class="form-control" name="performance_career[kpi]" rows="3" maxlength="2000">{{ old('performance_career.kpi',data_get($profile,'performance_career.kpi')) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Career plan</label><textarea class="form-control" name="performance_career[career_plan]" rows="3" maxlength="2000">{{ old('performance_career.career_plan',data_get($profile,'performance_career.career_plan')) }}</textarea></div>
                </div>
            </div>

            {{-- 8. Assets & Exit --}}
            <div class="nx-step-pane" data-pane="8">
                <div class="nx-step-help"><div><h2 class="nx-section-title mb-1">Assets, system access & separation</h2><p class="small text-secondary mb-0">Company property issued, and exit details when the time comes.</p></div><span class="nx-step-counter">Step 8 of 8</span></div>
                <div class="row g-3">
                    <div class="col-12"><div class="nx-field-group tone-teal">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="package-check"></i></span><div><strong>Assets issued</strong><small>Serial number, quantity or "Yes" is enough.</small></div></div>
                        <div class="row g-3">
                            @foreach(['laptop'=>'Laptop','mobile'=>'Company mobile','sim'=>'SIM','uniform'=>'Uniform','toolkit'=>'Toolkit','access_card'=>'Access card','email_access'=>'Email access','erp_access'=>'ERP access'] as $key=>$label)
                            @php $assetList = match ($key) { 'uniform', 'toolkit' => 'issue_statuses', 'email_access', 'erp_access' => 'access_statuses', default => null }; $assetValue = old('assets_access.'.$key,data_get($profile,'assets_access.'.$key)); @endphp
                            <div class="col-md-3"><label class="form-label">{{ $label }}</label>@if($assetList)<select class="form-select" name="assets_access[{{ $key }}]">{!! $opt($options($assetList), $assetValue) !!}</select>@else<input class="form-control" name="assets_access[{{ $key }}]" value="{{ $assetValue }}" maxlength="120" placeholder="Serial / number">@endif</div>
                            @endforeach
                        </div>
                    </div></div>
                    <div class="col-12"><div class="nx-field-group tone-slate">
                        <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="door-open"></i></span><div><strong>Separation</strong><small>Fill only when the employee is leaving.</small></div></div>
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Resignation date</label><input type="date" class="form-control" name="exit_separation[resignation_date]" value="{{ old('exit_separation.resignation_date',data_get($profile,'exit_separation.resignation_date')) }}" data-v="after_or_equal:date_of_joining"></div>
                            <div class="col-md-3"><label class="form-label">Last working date</label><input type="date" class="form-control" name="exit_separation[last_working_date]" value="{{ old('exit_separation.last_working_date',data_get($profile,'exit_separation.last_working_date')) }}" data-v="after_or_equal:resignation_date"></div>
                            <div class="col-md-6"><label class="form-label">Exit reason / clearance</label><textarea class="form-control" name="exit_separation[notes]" rows="2" maxlength="2000">{{ old('exit_separation.notes',data_get($profile,'exit_separation.notes')) }}</textarea></div>
                        </div>
                    </div></div>
                </div>
            </div>

            <div class="nx-wizard-actions mt-4 pt-3 border-top">
                <button type="button" class="btn btn-outline-secondary" id="employeeBack"><i data-lucide="arrow-left" style="width:16px"></i>Back</button>
                <div class="ms-auto d-flex gap-2 align-items-center">
                    <a href="{{ $employee ? route('employees.show',$employee) : route('employees.index') }}" class="btn btn-light border">Cancel</a>
                    @if($employee)<button class="btn btn-outline-primary" id="employeeSaveNow" type="submit"><i data-lucide="save" style="width:16px"></i>Save now</button>@endif
                    <button type="button" class="btn btn-primary" id="employeeNext">Next <i data-lucide="arrow-right" style="width:16px"></i></button>
                    <button class="btn btn-success d-none" id="employeeSubmit">{{ $employee?'Save employee':'Create employee & user' }}</button>
                </div>
            </div>
        </section>
    </form>
    @push('scripts')<script>
    document.addEventListener('DOMContentLoaded',()=>{
        const form=document.getElementById('employeeWizard'),steps=[...document.querySelectorAll('.nx-step')],panes=[...document.querySelectorAll('.nx-step-pane')],back=document.getElementById('employeeBack'),next=document.getElementById('employeeNext'),submit=document.getElementById('employeeSubmit'),progress=document.getElementById('employeeProgress');
        let current=0;
        const errorNames=@json(array_keys($errors->toArray()));
        const map={first_name:0,middle_name:0,last_name:0,local_script_name:0,languages:0,preferred_language:0,salutation:0,gender:0,date_of_birth:0,marital_status:0,anniversary_date:0,blood_group:0,nationality:0,religion:0,profile_picture:0,work_email:1,personal_email:1,phone:1,current_address:1,permanent_address:1,company_accommodation_address:1,emergency_family:1,employee_code:2,department:2,designation:2,role:2,premises:2,user_status:2,date_of_joining:2,employment_type:2,employee_category:2,job_grade:2,reporting_manager_id:2,functional_manager_id:2,hr_manager_id:2,identity_documents:3,verification:3,documents:3,salary_currency:4,pay_frequency:4,pay_group:4,basic_salary:4,hra:4,transport_allowance:4,fuel_allowance:4,other_allowance:4,gross_monthly_salary:4,bank_details:4,benefits_allowances:5,engagement_wellbeing:5,remarks:5,education_skills:6,safety:6,performance_career:6,assets_access:7,exit_separation:7};
        if(errorNames.length){current=Math.min(...errorNames.map(x=>map[x.split('.')[0]]??0));errorNames.forEach(name=>{const el=form.querySelector(`[name="${name}"]`)||form.querySelector(`[name^="${name.split('.')[0]}["]`);el?.classList.add('is-invalid')});errorNames.forEach(name=>steps[map[name.split('.')[0]]??0]?.classList.add('error'))}
        function show(i){current=Math.max(0,Math.min(i,panes.length-1));panes.forEach((p,x)=>p.classList.toggle('active',x===current));steps.forEach((s,x)=>{s.classList.toggle('active',x===current);if(x<current&&!s.classList.contains('error'))s.classList.add('done');if(x>=current)s.classList.remove('done')});back.disabled=current===0;next.classList.toggle('d-none',current===panes.length-1);submit.classList.toggle('d-none',current!==panes.length-1);if(progress)progress.style.width=((current+1)/panes.length*100)+'%';window.scrollTo({top:0,behavior:'smooth'})}
        steps.forEach((s,i)=>s.onclick=()=>show(i));
        back.onclick=()=>show(current-1);
        next.onclick=()=>{if(!NxValidate.validateContainer(panes[current])){steps[current].classList.add('error');return}steps[current].classList.remove('error');show(current+1)};
        form.addEventListener('nx:reveal',e=>{show(Number(e.detail.step)-1);e.preventDefault()});
        form.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target.tagName!=='TEXTAREA'&&current!==panes.length-1){e.preventDefault();next.click()}});
        show(current);

        // Languages known -> preferred app language
        const picker=document.getElementById('languagePicker'),preferred=document.getElementById('preferredLanguage');
        function syncLanguages(){const chosen=[...picker.querySelectorAll('input:checked')].map(i=>i.value);[...preferred.options].forEach(o=>{if(!o.value)return;o.hidden=chosen.length>0&&!chosen.includes(o.value)});if(preferred.value&&chosen.length&&!chosen.includes(preferred.value))preferred.value='';preferred.options[0].textContent=chosen.length?`Same as first selected (${chosen[0]})`:'Same as first selected language'}
        picker.addEventListener('change',syncLanguages);
        preferred.addEventListener('change',()=>{const box=picker.querySelector(`input[value="${preferred.value}"]`);if(box&&!box.checked){box.checked=true;syncLanguages()}});
        syncLanguages();

        // Anniversary only applies to married employees
        const marital=document.getElementById('maritalStatus'),anniversary=document.getElementById('anniversaryDate');
        function syncMarital(){const married=marital.value==='married';anniversary.disabled=!married;if(!married){anniversary.value='';anniversary.classList.remove('is-invalid')}}
        marital.addEventListener('change',syncMarital);syncMarital();

        // Department -> designation list
        const department=document.getElementById('employeeDepartment'),designation=document.getElementById('employeeDesignation'),byDepartment=JSON.parse(designation.dataset.designations||'{}');
        department.addEventListener('change',()=>{const current=designation.value,list=byDepartment[department.value]||[...new Set(Object.values(byDepartment).flat())];designation.replaceChildren(new Option('Select designation',''),...list.map(name=>new Option(name,name)));designation.value=list.includes(current)?current:'';designation.dispatchEvent(new Event('change',{bubbles:true}))});

        // Colour-coded status selects + live badges on verification cards
        document.querySelectorAll('.nx-status-select').forEach(select=>{const tones=JSON.parse(select.dataset.toneMap||'{}'),labels=JSON.parse(select.dataset.labelMap||'{}');const paint=()=>{const tone=tones[select.value]||'secondary';select.dataset.tone=tone;const badge=select.dataset.statusFor?document.querySelector(`[data-status-badge="${select.dataset.statusFor}"]`):null;if(badge){badge.className=`nx-verify-status tone-${tone} ms-auto`;badge.textContent=labels[select.value]||select.options[select.selectedIndex]?.text||''}};select.addEventListener('change',paint);paint()});

        // Copy current address into permanent address
        document.getElementById('copyCurrentAddress')?.addEventListener('click',()=>{['line1','city','pin'].forEach(part=>{const from=form.querySelector(`[name="current_address[${part}]"]`),to=form.querySelector(`[name="permanent_address[${part}]"]`);if(from&&to)to.value=from.value});const state=form.querySelector('[name="current_address[state]"]'),district=form.querySelector('[name="current_address[district]"]'),toState=form.querySelector('[name="permanent_address[state]"]'),toDistrict=form.querySelector('[name="permanent_address[district]"]');if(state&&toState&&state.value){if(![...toState.options].some(o=>o.value===state.value))toState.add(new Option(state.value,state.value));toState.value=state.value;toState.dispatchEvent(new Event('change',{bubbles:true}));window.setTimeout(()=>{if(district?.value&&toDistrict){if(![...toDistrict.options].some(o=>o.value===district.value))toDistrict.add(new Option(district.value,district.value));toDistrict.value=district.value;toDistrict.dispatchEvent(new Event('change',{bubbles:true}))}},400)}});

        // Salary component total helper
        const components=[...document.querySelectorAll('.salary-component')],total=document.getElementById('componentTotal'),gross=document.getElementById('grossMonthly');
        function sumComponents(){const sum=components.reduce((acc,el)=>acc+(parseFloat(el.value)||0),0);if(total)total.textContent=sum?`Components total: ${sum.toLocaleString('en-IN',{maximumFractionDigits:2})}`:'';if(gross&&(!gross.value||parseFloat(gross.value)===0)&&sum)gross.placeholder=sum.toFixed(2)}
        components.forEach(el=>el.addEventListener('input',sumComponents));sumComponents();

        // Document dropzone feedback
        const zone=document.getElementById('documentDropzone'),list=document.getElementById('documentList');
        if(zone){const input=zone.querySelector('input');['dragenter','dragover'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.add('is-over')}));['dragleave','drop'].forEach(ev=>zone.addEventListener(ev,()=>zone.classList.remove('is-over')));input.addEventListener('change',()=>{const files=[...input.files];list.textContent=files.length?`${files.length} file${files.length>1?'s':''} selected: ${files.map(f=>f.name).join(', ')}`:''})}
    });
    </script>@endpush
</x-app-layout>
