<!-- [ Main Content ] start -->
<div class="row">
    <div class="col-md-4">
      @if(is_file('uploads/'.$path.'/'.$row->photo))
      <img src="{{ asset('uploads/'.$path.'/'.$row->photo) }}" class="card-img-top img-fluid profile-thumb" alt="{{ __('field_photo') }}" onerror="this.src='{{ asset('dashboard/images/user/avatar-2.jpg') }}';">
      @else
      <img src="{{ asset('dashboard/images/user/avatar-2.jpg') }}" class="card-img-top img-fluid profile-thumb" alt="{{ __('field_photo') }}">
      @endif

      @php
        // Get current selected enrollment from session
        $selectedEnrollmentId = session('selected_enrollment_id');
        $enroll = \App\Models\StudentEnroll::where('id', $selectedEnrollmentId)
                    ->where('student_id', $row->id)
                    ->with(['program.faculty', 'session', 'semester', 'section'])
                    ->first();
        
        // Fallback to Student::enroll() if no session enrollment
        if(!$enroll) {
            $enroll = \App\Models\Student::enroll($row->id);
        }
      @endphp

      <br/><br/>
      <fieldset class="row gx-2 scheduler-border">
        <legend>{{ __('field_academic_information') }}</legend>
        <p><mark class="text-primary">{{ __('field_matricule') }}:</mark> 
            <strong style="font-size: 16px; color: #667eea;">{{ $enroll->matricule ?? $row->student_id }}</strong>
            @if($enroll && $enroll->program)
                <span class="badge" style="background: {{ $enroll->program->academic_level == 'M' ? '#f5576c' : ($enroll->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 10px; margin-left: 8px;">
                    {{ $enroll->program->academic_level == 'A' ? 'Undergraduate' : ($enroll->program->academic_level == 'M' ? 'Masters' : 'Doctoral') }}
                </span>
            @endif
        </p><hr/>
        <p><mark class="text-primary">{{ __('field_batch') }}:</mark> {{ $row->batch->title ?? '' }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_program') }}:</mark> {{ $enroll->program->title ?? $row->program->title ?? '' }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_session') }}:</mark> {{ $enroll->session->title ?? '' }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_semester') }}:</mark> {{ $enroll->semester->title ?? '' }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_section') }}:</mark> {{ $enroll->section->title ?? '' }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_status') }}:</mark> 
        @foreach($row->statuses as $key => $status)
            <span class="badge badge-primary">{{ $status->title }}</span>
        @endforeach
        </p><hr/>
        </fieldset>
    </div>

    <div class="col-md-4">
        <fieldset class="row gx-2 scheduler-border">
        <legend>{{ __('tab_profile_info') }}</legend>
        <p><mark class="text-primary">{{ __('field_student_id') }} (Internal):</mark> #{{ $row->student_id }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_name') }}:</mark> {{ $row->first_name }} {{ $row->last_name }}</p><hr/>
        @if(field('student_father_name')->status == 1)
        <p><mark class="text-primary">{{ __('field_father_name') }}:</mark> {{ $row->father_name }}</p><hr/>
        @endif
        @if(field('student_mother_name')->status == 1)
        <p><mark class="text-primary">{{ __('field_mother_name') }}:</mark> {{ $row->mother_name }}</p><hr/>
        @endif

        <p><mark class="text-primary">{{ __('field_email') }}:</mark> {{ $row->email }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_phone') }}:</mark> {{ $row->phone }}</p><hr/>

        <p><mark class="text-primary">{{ __('field_gender') }}:</mark> 
            @if( $row->gender == 1 )
            {{ __('gender_male') }}
            @elseif( $row->gender == 2 )
            {{ __('gender_female') }}
            @elseif( $row->gender == 3 )
            {{ __('gender_other') }}
            @endif
        </p><hr/>

        <p><mark class="text-primary">{{ __('field_dob') }}:</mark> 
            @if(isset($setting->date_format))
            {{ date($setting->date_format, strtotime($row->dob)) }}
            @else
            {{ date("Y-m-d", strtotime($row->dob)) }}
            @endif
        </p><hr/>

        @if(field('student_marital_status')->status == 1)
        <p><mark class="text-primary">{{ __('field_marital_status') }}:</mark> 
            @if( $row->marital_status == 1 )
            {{ __('marital_status_single') }}
            @elseif( $row->marital_status == 2 )
            {{ __('marital_status_married') }}
            @elseif( $row->marital_status == 3 )
            {{ __('marital_status_widowed') }}
            @elseif( $row->marital_status == 4 )
            {{ __('marital_status_divorced') }}
            @elseif( $row->marital_status == 5 )
            {{ __('marital_status_other') }}
            @endif
        </p><hr/>
        @endif

        @if(field('student_blood_group')->status == 1)
        <p><mark class="text-primary">{{ __('field_blood_group') }}:</mark> 
            @if( $row->blood_group == 1 )
            {{ __('A+') }}
            @elseif( $row->blood_group == 2 )
            {{ __('A-') }}
            @elseif( $row->blood_group == 3 )
            {{ __('B+') }}
            @elseif( $row->blood_group == 4 )
            {{ __('B-') }}
            @elseif( $row->blood_group == 5 )
            {{ __('AB+') }}
            @elseif( $row->blood_group == 6 )
            {{ __('AB-') }}
            @elseif( $row->blood_group == 7 )
            {{ __('O+') }}
            @elseif( $row->blood_group == 8 )
            {{ __('O-') }}
            @endif
        </p><hr/>
        @endif
    </div>

    <div class="col-md-4">
        @if(field('student_address')->status == 1)
        <fieldset class="row gx-2 scheduler-border">
        <legend>{{ __('field_present') }} {{ __('field_address') }}</legend>
        <p><mark class="text-primary">{{ __('field_province') }}:</mark> {{ $row->present_province ?? '' }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_district') }}:</mark> {{ $row->present_district ?? '' }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_address') }}:</mark> {{ $row->present_address }}</p>
        </fieldset>

        <fieldset class="row gx-2 scheduler-border">
        <legend>{{ __('field_permanent') }} {{ __('field_address') }}</legend>
        <p><mark class="text-primary">{{ __('field_province') }}:</mark> {{ $row->permanent_province ?? '' }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_district') }}:</mark> {{ $row->permanent_district ?? '' }}</p><hr/>
        <p><mark class="text-primary">{{ __('field_address') }}:</mark> {{ $row->permanent_address }}</p>
        </fieldset>
        @endif
    </div>
</div>
<!-- [ Main Content ] end -->