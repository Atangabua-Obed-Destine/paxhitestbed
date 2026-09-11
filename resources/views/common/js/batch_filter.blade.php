<!-- Filter Search -->
<script type="text/javascript">
    "use strict";
    // Faculty filter handler
    $(".faculty").on('change',function(e){
      e.preventDefault(e);
      var $faculty = $(this);
      var $container = $faculty.closest('form');
      if(!$container.length){
        $container = $(document);
      }
      var program=$container.find(".program");
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
            program.each(function(){
              var $program = $(this);
              var selectedValue = $program.data('selected');
              $('option', $program).remove();
              $program.append('<option value="">{{ __("select") }}</option>');
              $.each(response, function(){
                $('<option/>', {
                  'value': this.id,
                  'text': this.title
                }).appendTo($program);
              });
              if(selectedValue){
                $program.val(selectedValue);
                $program.data('selected', '');
                $program.trigger('change');
              }
            });
          }

      });
    });

    $(".batch").on('change',function(e){
      e.preventDefault(e);
      var $batch = $(this);
      var $container = $batch.closest('form');
      if(!$container.length){
        $container = $(document);
      }
      var program=$container.find(".program");
      $.ajaxSetup({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
      });
      $.ajax({
        type:'POST',
        url: "{{ route('filter-batch') }}",
        data:{
          _token:$('input[name=_token]').val(),
          batch:$(this).val()
        },
        success:function(response){
            // var jsonData=JSON.parse(response);
            program.each(function(){
              var $program = $(this);
              var selectedValue = $program.data('selected');
              $('option', $program).remove();
              $program.append('<option value="">{{ __("select") }}</option>');
              $.each(response, function(){
                $('<option/>', {
                  'value': this.id,
                  'text': this.title
                }).appendTo($program);
              });
              if(selectedValue){
                $program.val(selectedValue);
                $program.data('selected', '');
              }
            });
          }

      });
    });

    $(".program").on('change',function(e){
      e.preventDefault(e);
      var $program = $(this);
      var $container = $program.closest('form');
      if(!$container.length){
        $container = $(document);
      }
      var session=$container.find(".session");
      var semester=$container.find(".semester");
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
            session.each(function(){
              var $session = $(this);
              var selectedValue = $session.data('selected');
              $('option', $session).remove();
              $session.append('<option value="">{{ __("select") }}</option>');
              $.each(response, function(){
                $('<option/>', {
                  'value': this.id,
                  'text': this.title
                }).appendTo($session);
              });
              if(selectedValue){
                $session.val(selectedValue);
                $session.data('selected', '');
              }
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
            // var jsonData=JSON.parse(response);
            semester.each(function(){
              var $semester = $(this);
              var selectedValue = $semester.data('selected');
              $('option', $semester).remove();
              $semester.append('<option value="">{{ __("select") }}</option>');
              $.each(response, function(){
                $('<option/>', {
                  'value': this.id,
                  'text': this.title
                }).appendTo($semester);
              });
              if(selectedValue){
                $semester.val(selectedValue);
                $semester.data('selected', '');
                $semester.trigger('change');
              }
              // Opt-in only: a select asks for this with
              // data-default="first-non-resit". Every other screen is
              // untouched, and an explicit choice always wins.
              else if($semester.data('default') === 'first-non-resit'){
                var ordered = $.grep(response, function(row){
                  return !row.is_resit || row.is_resit === '0';
                }).sort(function(a, b){
                  var year = (parseInt(a.year, 10) || 0) - (parseInt(b.year, 10) || 0);
                  return year !== 0 ? year : (a.id - b.id);
                });

                if(ordered.length){
                  $semester.val(ordered[0].id);
                  $semester.trigger('change');
                }
              }
            });
          }

      });
    });

    $(".semester").on('change',function(e){
      e.preventDefault(e);
      var $semester = $(this);
      var $container = $semester.closest('form');
      if(!$container.length){
        $container = $(document);
      }
  var section=$container.find(".section");
  var programId = $container.find('.program').val();
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
          program:programId
        },
        success:function(response){
            // var jsonData=JSON.parse(response);
            section.each(function(){
              var $section = $(this);
              var selectedValue = $section.data('selected');
              $('option', $section).remove();
              $section.append('<option value="">{{ __("select") }}</option>');
              $.each(response, function(){
                $('<option/>', {
                  'value': this.id,
                  'text': this.title
                }).appendTo($section);
              });
              if(selectedValue){
                $section.val(selectedValue);
                $section.data('selected', '');
              }
              // Opt-in only, with data-default="all": the section named "All",
              // or the only section there is. Anything else is left for the
              // admin to choose.
              else if($section.data('default') === 'all'){
                var all = $.grep(response, function(row){
                  return $.trim(String(row.title)).toLowerCase() === 'all';
                });

                if(all.length){
                  $section.val(all[0].id);
                }
                else if(response.length === 1){
                  $section.val(response[0].id);
                }
              }
              $section.trigger('change');
            });
          }

      });
    });
</script>