<div class="form-group col-md-3">
  <label for="faculty">{{ __('field_faculty') }} <span>*</span></label>
  <select class="form-control faculty" name="faculty" id="faculty">
		<option value="0">{{ __('all') }}</option>
		@if(isset($faculties))
		@foreach( $faculties->sortBy('title') as $faculty )
		<option value="{{ $faculty->id }}" @if( $selected_faculty == $faculty->id) selected @endif>{{ $faculty->title }}</option>
		@endforeach
		@endif
	</select>

	<div class="invalid-feedback">
		{{ __('required_field') }} {{ __('field_faculty') }}
	</div>
</div>
<div class="form-group col-md-3">
  <label for="program">{{ __('field_program') }} <span>*</span></label>
  <select class="form-control program" name="program" id="program">
		<option value="0">{{ __('all') }}</option>
		@if(isset($programs))
		@foreach( $programs->sortBy('title') as $program )
		<option value="{{ $program->id }}" @if( $selected_program == $program->id) selected @endif>{{ $program->title }}</option>
		@endforeach
		@endif
	</select>

	<div class="invalid-feedback">
		{{ __('required_field') }} {{ __('field_program') }}
	</div>
</div>
<div class="form-group col-md-3">
  <label for="session">{{ __('field_session') }} <span>*</span></label>
  <select class="form-control session" name="session" id="session">
		<option value="0">{{ __('all') }}</option>
		@if(isset($sessions))
		@foreach( $sessions->sortByDesc('id') as $session )
		<option value="{{ $session->id }}" @if( $selected_session == $session->id) selected @endif>{{ $session->title }}</option>
		@endforeach
		@endif
	</select>

	<div class="invalid-feedback">
		{{ __('required_field') }} {{ __('field_session') }}
	</div>
</div>
{{--
  The Year select narrows the Semester select beside it; it is not a filter of
  its own, and none of the controllers behind this partial read semester_year.
  A screen that does not want it passes show_semester_year => false, and the
  Semester select then keeps the full server-rendered list instead of being
  emptied and rebuilt per year.

  Worth knowing before enabling it on a new screen: the Year select carries
  `required`, and form-validation.js blocks submit on any .needs-validation
  form that fails checkValidity(). Since the list is empty until a programme
  is chosen, leaving it on a screen that can be searched unfiltered blocks the
  search button on an inert field.
--}}
@if($show_semester_year ?? true)
@include('common.inc.semester_year_field')
@endif
<div class="form-group col-md-3">
  <label for="semester">{{ __('field_semester') }} <span>*</span></label>
  <select class="form-control semester" name="semester" id="semester">
		<option value="0">{{ __('all') }}</option>
		@if(isset($semesters))
		@foreach( $semesters->sortBy('id') as $semester )
		<option value="{{ $semester->id }}" @if( $selected_semester == $semester->id) selected @endif>{{ $semester->title }}</option>
		@endforeach
		@endif
	</select>

	<div class="invalid-feedback">
		{{ __('required_field') }} {{ __('field_semester') }}
	</div>
</div>
<div class="form-group col-md-3">
  <label for="section">{{ __('field_section') }} <span>*</span></label>
	<select class="form-control section" name="section" id="section">
		<option value="0">{{ __('all') }}</option>
		@if(isset($sections))
		@foreach( $sections->sortBy('title') as $section )
		<option value="{{ $section->id }}" @if( $selected_section == $section->id) selected @endif>{{ $section->title }}</option>
		@endforeach
		@endif
	</select>

	<div class="invalid-feedback">
		{{ __('required_field') }} {{ __('field_section') }}
	</div>
</div>


<script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>

<script type="text/javascript">
    "use strict";
    $(".faculty").on('change',function(e){
      e.preventDefault(e);
      var program=$(".program");
      $.ajaxSetup({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
      });
      $.ajax({
        type:'POST',
        url: "{{ route('filter-program') }}",
        data:{
          _token:$('input[name=_token]').val(),
          faculty:$(this).val()
        },
        success:function(response){
              console.log("Okk");
            // var jsonData=JSON.parse(response);
            $('option', program).remove();
            $('.program').append('<option value="">{{ __("all") }}</option>');
            $.each(response, function(){
              $('<option/>', {
                'value': this.id,
                'text': this.title
              }).appendTo('.program');
            });
          }

      });
    });

    $(".program").on('change',function(e){
      e.preventDefault(e);
      var session=$(".session");
      var semester=$(".semester");
      $.ajaxSetup({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
      });
      $.ajax({
        type:'POST',
        url: "{{ route('filter-session') }}",
        data:{
          _token:$('input[name=_token]').val(),
          program:$(this).val()
        },
        success:function(response){
            // var jsonData=JSON.parse(response);
            $('option', session).remove();
            $('.session').append('<option value="">{{ __("all") }}</option>');
            $.each(response, function(){
              $('<option/>', {
                'value': this.id,
                'text': this.title
              }).appendTo('.session');
            });
          }

      });

      $.ajax({
        type:'POST',
        url: "{{ route('filter-semester') }}",
        data:{
          _token:$('input[name=_token]').val(),
          program:$(this).val()
        },
        success:function(response){
            // Handed to the shared year/semester module, which regroups the
            // flat response by year and keeps the Year select in step. The
            // endpoint already returns `year` on every row, so nothing on the
            // server had to change for this.
            var handle = $('.semester').data('semesterYearHandle');
            if (handle) {
              handle.setLookup(response);
            } else {
              $('option', semester).remove();
              $('.semester').append('<option value="">{{ __("all") }}</option>');
              $.each(response, function(){
                $('<option/>', {
                  'value': this.id,
                  'text': this.title
                }).appendTo('.semester');
              });
            }
          }

      });
    });

    $(".semester").on('change',function(e){
      e.preventDefault(e);
      var section=$(".section");
      $.ajaxSetup({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
      });
      $.ajax({
        type:'POST',
        url: "{{ route('filter-section') }}",
        data:{
          _token:$('input[name=_token]').val(),
          semester:$(this).val(),
          program:$('.program option:selected').val()
        },
        success:function(response){
            // var jsonData=JSON.parse(response);
            $('option', section).remove();
            $('.section').append('<option value="">{{ __("all") }}</option>');
            $.each(response, function(){
              $('<option/>', {
                'value': this.id,
                'text': this.title
              }).appendTo('.section');
            });
          }

      });
    });
</script>