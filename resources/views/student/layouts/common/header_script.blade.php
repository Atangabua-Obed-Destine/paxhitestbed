        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="robots" content="noindex" />
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @if(isset($setting))
        <!-- App Title -->
        <title>@yield('title') | {{ $setting->meta_title ?? '' }}</title>

        <meta name="description" content="{!! str_limit(strip_tags($setting->meta_description), 160, ' ...') !!}">
        <meta name="keywords" content="{!! strip_tags($setting->meta_keywords) !!}">

        @if(upload_exists('setting/'.$setting->favicon_path))
        <!-- App favicon -->
        <link rel="shortcut icon" href="{{ upload_asset('setting/'.$setting->favicon_path) }}" type="image/x-icon">
        @endif
        @endif

        @if(empty($setting))
        <!-- App Title -->
        <title>@yield('title')</title>
        @endif

        <!-- fontawesome icon -->
        <link rel="stylesheet" href="{{ asset('dashboard/fonts/fontawesome/css/fontawesome-all.min.css') }}">
        <!-- data tables css -->
        <link rel="stylesheet" href="{{ asset('dashboard/plugins/data-tables/css/datatables.min.css') }}">
        <!-- material datetimepicker css -->
        <link rel="stylesheet" href="{{ asset('dashboard/plugins/material-datetimepicker/css/bootstrap-material-datetimepicker.css') }}">
        <!-- toastr css -->
        <link rel="stylesheet" href="{{ asset('dashboard/plugins/toastr/css/toastr.min.css') }}">

        
        <!-- page css -->
        @yield('page_css')


        <!-- vendor css -->
        <link rel="stylesheet" href="{{ asset('dashboard/css/style.css') }}">

        <!-- Student Header & Sidebar — Modern Design -->
        <link rel="stylesheet" href="{{ asset('dashboard/css/student-header-responsive.css') }}">

        {{-- Page specific styles --}}
        @stack('styles')

        <!-- Custom notification badge styles -->
        <style>
            .notification-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                min-width: 18px;
                height: 18px;
                padding: 2px 5px;
                font-size: 10px;
                font-weight: bold;
                line-height: 14px;
                border-radius: 9px;
                z-index: 1;
            }
            .icon.feather.icon-bell {
                position: relative;
            }
        </style>

        @php 
        $version = App\Models\Language::version(); 
        @endphp
        @if($version->direction == 1)
        <!-- RTL css -->
        <link rel="stylesheet" class="rtl-css" href="{{ asset('dashboard/css/layouts/rtl.css') }}">
        @endif