        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="robots" content="noindex" />
        <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Installing the portal.

         The manifest and the worker both live under the student prefix, so the
         worker's scope is the student portal and it cannot touch an admin or
         applicant page. --}}
    @if(config('pwa.enabled'))
        <link rel="manifest" href="{{ route('student.pwa.manifest') }}">
        <meta name="theme-color" content="{{ config('pwa.theme_color') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ $setting->title ?? config('app.name') }}">
        <link rel="apple-touch-icon" href="{{ asset('pwa/icon-192.png') }}">
    @endif


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