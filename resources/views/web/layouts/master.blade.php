<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
 <head>
 	<!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="{{ $setting->title ?? 'PAX Higher Institute' }}">
    <meta name="theme-color" content="#109bff">

    @if(isset($setting))
    <!-- App Title -->
    <title>@yield('title') | {{ $setting->meta_title ?? '' }}</title>

    <meta name="description" content="{!! str_limit(strip_tags($setting->meta_description), 160, ' ...') !!}">
    <meta name="keywords" content="{!! strip_tags($setting->meta_keywords) !!}">

    <!-- App favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('/uploads/setting/'.$setting->favicon_path) }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ asset('/uploads/setting/'.$setting->favicon_path) }}" type="image/x-icon">
    @endif


    @if(empty($setting))
    <!-- App Title -->
    <title>@yield('title')</title>
    @endif


    <!-- Social Meta Tags -->
    @yield('social_meta_tags')

    <!-- Schema.org Structured Data -->
    @if(isset($setting))
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "{{ $setting->title }}",
        "url": "{{ route('home') }}",
        "logo": "{{ asset('/uploads/setting/'.$setting->logo_path) }}",
        @if(isset($setting->meta_description))
        "description": "{!! str_limit(strip_tags($setting->meta_description), 160, '...') !!}",
        @endif
        @if(isset($topbarSetting))
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "{{ $topbarSetting->phone ?? '' }}",
            "email": "{{ $topbarSetting->email ?? '' }}",
            "contactType": "admissions"
        },
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "{{ $topbarSetting->address ?? '' }}"
        },
        @endif
        "sameAs": [
            @if(isset($socialSetting->facebook))"{{ $socialSetting->facebook }}",@endif
            @if(isset($socialSetting->twitter))"{{ $socialSetting->twitter }}",@endif
            @if(isset($socialSetting->linkedin))"{{ $socialSetting->linkedin }}",@endif
            @if(isset($socialSetting->instagram))"{{ $socialSetting->instagram }}",@endif
            @if(isset($socialSetting->youtube))"{{ $socialSetting->youtube }}"@endif
        ]
    }
    </script>
    @endif

    @yield('structured_data')

    <!-- Hreflang Tags for Multi-Language Support -->
    @if(isset($user_languages) && $user_languages->count() > 0)
        @foreach($user_languages as $user_language)
            <link rel="alternate" hreflang="{{ $user_language->code }}" href="{{ url()->current() }}?lang={{ $user_language->code }}" />
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}" />
    @endif

    <!-- Sitemap -->
    <link rel="sitemap" type="application/xml" title="Sitemap" href="{{ route('sitemap') }}" />


 	<!-- Stylesheets -->
 	<link rel="stylesheet" href="{{ asset('web/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('web/css/animate.min.css') }}">
    <link rel="stylesheet" href="{{ asset('web/css/magnific-popup.css') }}">
    <link rel="stylesheet" href="{{ asset('web/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('web/css/dripicons.css') }}">
    <link rel="stylesheet" href="{{ asset('web/css/slick.css') }}">
    <link rel="stylesheet" href="{{ asset('web/css/meanmenu.css') }}">
    <link rel="stylesheet" href="{{ asset('web/css/default.css') }}">
    <link rel="stylesheet" href="{{ asset('web/css/style.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('web/css/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('web/css/custom.css') }}?v={{ time() }}">
    <!-- Resources & Downloads Styling -->
    <link rel="stylesheet" href="{{ asset('web/css/resources-downloads.css') }}?v={{ time() }}">
    <!-- AOS Animation Library -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">



    @php 
    $version = App\Models\Language::version(); 
    @endphp
    @if($version->direction == 1)
    <!-- RTL css -->
    <link rel="stylesheet" href="{{ asset('web/css/rtl.css') }}">
    @endif
 </head>

 <body>
    @php
        // Ensure announcements available on all pages when possible
        if(!isset($announcements)){
            try{
                $today = now()->toDateString();
                $announcements = \App\Models\Web\Announcement::where('status', 1)
                    ->where(function($q) use ($today){
                        $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
                    })
                    ->where(function($q) use ($today){
                        $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
                    })
                    ->where(function($q){
                        $q->where('language_id', App\Models\Language::version()->id)->orWhereNull('language_id');
                    })
                    ->orderByDesc('start_date')
                    ->get();
            } catch (\Exception $e) {
                $announcements = collect();
            }
        }
    @endphp

    @if(isset($announcements) && $announcements->count())
    @php
        // Calculate dynamic animation duration based on number of announcements
        // Formula: 25 seconds base + 15 seconds per announcement (very slow, very readable)
        $announcementCount = $announcements->count();
        $animationDuration = 25 + ($announcementCount * 15);
        $mobileAnimationDuration = 22 + ($announcementCount * 12);
    @endphp
    <div class="site-announcement" style="position:fixed;top:0;left:0;right:0;background:#fff8e1;border-bottom:1px solid #eee;overflow:hidden;z-index:10000;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
        <div class="container">
            <div class="announcement-ticker-wrapper" style="position:relative;padding:10px 0;">
                <div class="announcement-ticker" id="announcementTicker">
                    @foreach($announcements as $a)
                        <span class="announcement-item" style="display:inline-block;padding:0 40px;color:#333;font-weight:600;white-space:nowrap;">
                            {!! $a->message !!}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <style>
        .announcement-ticker-wrapper {
            overflow: hidden;
            width: 100%;
        }
        .announcement-ticker {
            display: inline-block;
            white-space: nowrap;
            animation: scroll-left {{ $animationDuration }}s linear infinite;
            animation-delay: 0s; /* Start immediately */
        }
        .announcement-ticker:hover {
            animation-play-state: paused;
        }
        @keyframes scroll-left {
            0% {
                transform: translateX(20%); /* Start even closer - minimal delay */
            }
            100% {
                transform: translateX(-100%);
            }
        }
        /* Adjust header to start below announcement bar */
        .header-area {
            margin-top: 50px !important;
        }
        /* When header becomes sticky, position it below the announcement bar */
        .header-area.sticky-menu {
            top: 50px !important;
        }
        #header-sticky.sticky-menu {
            top: 50px !important;
        }
        @media (max-width: 768px) {
            .announcement-ticker {
                animation-duration: {{ $mobileAnimationDuration }}s;
            }
            .announcement-item {
                padding: 0 20px !important;
                font-size: 14px;
            }
            .header-area {
                margin-top: 45px !important;
            }
            .header-area.sticky-menu {
                top: 45px !important;
            }
            #header-sticky.sticky-menu {
                top: 45px !important;
            }
        }
    </style>
    @endif

 	<!-- header -->
    <header class="header-area header-three">  
       <div class="header-top second-header d-none d-md-block">
            <div class="container">
                <div class="row align-items-center">      
                   
                    <div class="col-lg-4 col-md-4 d-none d-lg-block ">
                        @if(isset($topbarSetting) && $topbarSetting->social_status == 1)
                        <div class="header-social">
                            <span>
                            @if(isset($socialSetting->facebook))
                            <a href="{{ $socialSetting->facebook }}" target="_blank"><i class="fab fa-facebook-f"></i></a>
                            @endif
                            @if(isset($socialSetting->instagram))
                            <a href="{{ $socialSetting->instagram }}" target="_blank"><i class="fab fa-instagram"></i></a>
                            @endif
                            @if(isset($socialSetting->twitter))
                            <a href="{{ $socialSetting->twitter }}" target="_blank"><i class="fab fa-twitter"></i></a>
                            @endif
                            @if(isset($socialSetting->linkedin))
                            <a href="{{ $socialSetting->linkedin }}" target="_blank"><i class="fab fa-linkedin-in"></i></a>
                            @endif
                            @if(isset($socialSetting->pinterest))
                            <a href="{{ $socialSetting->pinterest }}" target="_blank"><i class="fab fa-pinterest"></i></a>
                            @endif
                            @if(isset($socialSetting->youtube))
                            <a href="{{ $socialSetting->youtube }}" target="_blank"><i class="fab fa-youtube"></i></a>
                            @endif
                           </span>                    
                           <!--  /social media icon redux -->                               
                        </div>
                        @endif
                    </div>

                    <div class="col-lg-8 col-md-8 d-none d-lg-block text-right">
                        <div class="header-cta">
                            <ul>
                               @isset($topbarSetting->phone)
                               <li>
                                  <div class="call-box">
                                     <div class="icon">
                                        <img src="{{ asset('web/img/icon/phone-call.png') }}" alt="img">
                                     </div>
                                     <div class="text">
                                        <strong><a href="tel:{{ str_replace(' ', '', $topbarSetting->phone ?? '') }}">{{ $topbarSetting->phone ?? '' }}</a></strong>
                                     </div>
                                  </div>
                               </li>
                               @endisset
                               @isset($topbarSetting->email)
                               <li>
                                  <div class="call-box">
                                     <div class="icon">
                                        <img src="{{ asset('web/img/icon/mailing.png') }}" alt="img">
                                     </div>
                                     <div class="text">
                                        <strong><a href="mailto:{{ $topbarSetting->email ?? '' }}">{{ $topbarSetting->email ?? '' }}</a></strong>
                                     </div>
                                  </div>
                               </li>
                               @endisset
                            </ul>
                        </div>                        
                    </div>
                    
                </div>
            </div>
        </div>    


        <div id="header-sticky" class="menu-area">
            <div class="container">
                <div class="second-menu">
                    <div class="row align-items-center">
                        <div class="col-xl-3 col-lg-3">
                            @if(isset($setting))
                            <div class="logo">
                                <a href="{{ route('home') }}"><img src="{{ asset('/uploads/setting/'.$setting->logo_path) }}" alt="logo"></a>
                            </div>
                            @endif
                        </div>

                        <div class="col-xl-6 col-lg-6">
                            <div class="main-menu text-right text-xl-right">
                                <nav id="mobile-menu">
                                    <ul>
                                        <li class="{{ Request::path() == '/' || Request::path() == 'home' ? 'current' : '' }}"><a href="{{ route('home') }}">{{ __('navbar_home') }}</a></li>
                                        <li class="{{ Request::is('about*') ? 'current' : '' }}"><a href="{{ route('about') }}">About</a></li>
                                        <li class="{{ Request::is('programs*') ? 'current' : '' }}"><a href="{{ route('programs') }}">Programs</a></li>
                                        <li class="{{ Request::is('faculties*') ? 'current' : '' }}"><a href="{{ route('faculties') }}">Faculties</a></li>
                                        <li class="{{ Request::is('admissions*') ? 'current' : '' }}"><a href="{{ route('admissions') }}">Admissions</a></li>
                                        <li class="{{ Request::is('projects*') ? 'current' : '' }}"><a href="{{ route('projects') }}">Projects</a></li>
                                        <li class="{{ Request::is('campus-life*') ? 'current' : '' }}"><a href="{{ route('campus-life') }}">Campus Life</a></li>
                                        <li class="{{ Request::is('student/e-library*') ? 'current' : '' }}"><a href="{{ route('student.e-library.index') }}">E-Library</a></li>
                                    </ul>
                                </nav>
                            </div>
                        </div>

                        <div class="col-xl-3 col-lg-3 text-right d-none d-lg-block text-right text-xl-right">
                            @php 
                            $application = App\Models\ApplicationSetting::status(); 
                            @endphp
                            @isset($application)
                            <div class="login">
                                <ul>
                                    <li>
                                        <div class="second-header-btn">
                                           <a href="{{ route('application.index') }}" target="_blank" class="btn">{{ __('navbar_admission') }}</a>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            @endisset
                        </div>
                        
                        <div class="col-12">
                            <div class="mobile-menu"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- header-end -->

 	
    <!-- Content Start -->
    @yield('content')
    <!-- Content End -->


 	<!-- footer -->
    <footer class="footer-bg footer-p pt-90" style="background-color: #109bff;">
        <div class="footer-top pb-70">
            <div class="container">
                <div class="row justify-content-between">
                    
                    <div class="col-xl-4 col-lg-4 col-sm-12">
                        <div class="footer-widget mb-30">
                            <div class="f-widget-title">
                                <h2>{{ __('footer_socials') }}</h2>
                            </div>
                            <div class="footer-social mt-10">                                    
                                @if(isset($socialSetting->facebook))
                                <a href="{{ $socialSetting->facebook }}" target="_blank"><i class="fab fa-facebook-f"></i></a>
                                @endif
                                @if(isset($socialSetting->instagram))
                                <a href="{{ $socialSetting->instagram }}" target="_blank"><i class="fab fa-instagram"></i></a>
                                @endif
                                @if(isset($socialSetting->twitter))
                                <a href="{{ $socialSetting->twitter }}" target="_blank"><i class="fab fa-twitter"></i></a>
                                @endif
                                @if(isset($socialSetting->linkedin))
                                <a href="{{ $socialSetting->linkedin }}" target="_blank"><i class="fab fa-linkedin-in"></i></a>
                                @endif
                                @if(isset($socialSetting->pinterest))
                                <a href="{{ $socialSetting->pinterest }}" target="_blank"><i class="fab fa-pinterest"></i></a>
                                @endif
                                @if(isset($socialSetting->youtube))
                                <a href="{{ $socialSetting->youtube }}" target="_blank"><i class="fab fa-youtube"></i></a>
                                @endif
                            </div>    
                        </div>
                    </div>

                    <div class="col-xl-4 col-lg-4 col-sm-6">
                        <div class="footer-widget mb-30">
                            <div class="f-widget-title">
                                <h2>{{ __('footer_links') }}</h2>
                            </div>
                            <div class="footer-link">
                                <ul>
                                    @if (Route::has('student.login'))
                                    <li><a href="{{ route('student.login') }}" target="_blank">{{ __('field_student') }} {{ __('field_login') }}</a></li>
                                    @endif
                                    @if (Route::has('login'))
                                    <li><a href="{{ route('login') }}" target="_blank">{{ __('field_staff') }} {{ __('field_login') }}</a></li>
                                    @endif

                                    @php 
                                    $application = App\Models\ApplicationSetting::status(); 
                                    @endphp
                                    @isset($application)
                                    <li><a href="{{ route('application.index') }}" target="_blank">{{ __('navbar_admission') }}</a></li>
                                    @endisset

                                    @foreach($footer_pages as $footer_page)
                                    <li><a href="{{ route('page.single', ['slug' => $footer_page->slug]) }}">{{ $footer_page->title }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Downloads Section -->
                    @php
                        $footerResources = \App\Models\Web\Resource::where('language_id', \App\Models\Language::version()->id)
                            ->where('status', 1)
                            ->whereIn('category', ['student_guide', 'calendarium'])
                            ->orderBy('sort_order', 'asc')
                            ->take(4)
                            ->get();
                    @endphp
                    @if($footerResources->count() > 0)
                    <div class="col-xl-4 col-lg-4 col-sm-6">
                        <div class="footer-widget mb-30">
                            <div class="f-widget-title">
                                <h2><i class="fas fa-download me-1"></i> Quick Downloads</h2>
                            </div>
                            <div class="footer-link footer-downloads">
                                <ul>
                                    @foreach($footerResources as $fResource)
                                    <li>
                                        <a href="{{ route('resource.download', $fResource->id) }}" title="Download {{ $fResource->title }}">
                                            <i class="{{ $fResource->file_icon }} me-1"></i> {{ Str::limit($fResource->title, 30) }}
                                            <span class="download-badge">{{ strtoupper(pathinfo($fResource->file_path, PATHINFO_EXTENSION)) }}</span>
                                        </a>
                                    </li>
                                    @endforeach
                                    <li class="mt-2">
                                        <a href="{{ route('resources') }}" class="view-all-link">
                                            <i class="fas fa-folder-open me-1"></i> View All Resources →
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="col-xl-4 col-lg-4 col-sm-6">
                        <div class="footer-widget mb-30">
                            <div class="f-widget-title">
                                <h2>{{ __('footer_contact') }}</h2>
                            </div>
                            <div class="f-contact">
                                <ul>
                                    @isset($topbarSetting->phone)
                                    <li>
                                        <i class="icon fal fa-phone"></i>
                                        <span><a href="tel:{{ str_replace(' ', '', $topbarSetting->phone ?? '') }}">{{ $topbarSetting->phone ?? '' }}</a></span>
                                    </li>
                                    @endisset
                                    @isset($topbarSetting->email)
                                    <li>
                                        <i class="icon fal fa-envelope"></i>
                                        <span><a href="mailto:{{ $topbarSetting->email ?? '' }}">{{ $topbarSetting->email ?? '' }}</a></span>
                                    </li>
                                    @endisset
                                    @isset($topbarSetting->address)
                                    <li>
                                        <i class="icon fal fa-map-marker-check"></i>
                                        <span>{{ $topbarSetting->address ?? '' }}</span>
                                    </li>
                                    @endisset
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>


        <div class="copyright-wrap">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-4 col-md-4 col-12">
                        <div class="dropdown">
                          <a class="btn dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ $version->name }}
                          </a>

                          <ul class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                            @foreach($user_languages as $user_language)
                            <li><a class="dropdown-item" href="{{ route('version', $user_language->code) }}">{{ $user_language->name }}</a></li>
                            @endforeach
                          </ul>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4 col-12 text-center">          
                        
                    </div>
                    <div class="col-lg-4 col-md-4 col-12 text-center text-md-right">
                        @isset($setting->copyright_text)
                        &copy; {!! strip_tags($setting->copyright_text, '<a><b><i><u><strong>') !!}
                        @endisset
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <!-- footer-end -->


 	<!-- Script JS -->
 	<script src="{{ asset('web/js/vendor/modernizr-3.5.0.min.js') }}"></script>
    <script src="{{ asset('web/js/vendor/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('web/js/popper.min.js') }}"></script>
    <script src="{{ asset('web/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('web/js/slick.min.js') }}"></script>
    <script src="{{ asset('web/js/paroller.js') }}"></script>
    <script src="{{ asset('web/js/wow.min.js') }}"></script>
    <script src="{{ asset('web/js/js_isotope.pkgd.min.js') }}"></script>
    <script src="{{ asset('web/js/imagesloaded.min.js') }}"></script>
    <script src="{{ asset('web/js/jquery.waypoints.min.js') }}"></script>
    <script src="{{ asset('web/js/jquery.countdown.min.js') }}"></script>
    <script src="{{ asset('web/js/jquery.counterup.min.js') }}"></script>
    <script src="{{ asset('web/js/jquery.scrollUp.min.js') }}"></script>
    <script src="{{ asset('web/js/jquery.meanmenu.min.js') }}"></script>
    <script src="{{ asset('web/js/parallax-scroll.js') }}"></script>
    <script src="{{ asset('web/js/jquery.magnific-popup.min.js') }}"></script>
    <script src="{{ asset('web/js/element-in-view.js') }}"></script>
    <script src="{{ asset('web/js/main.js') }}"></script>
    <script src="{{ asset('web/js/fix-banner.js') }}"></script>
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });
    </script>
    
    @yield('script')
    
    {{-- Dynamic Popup Component for Front Web --}}
    @include('components.dynamic-popup', ['area' => 'front_web'])
    
    {{-- Chatbot temporarily disabled - uncomment to enable
    <script defer src="https://chatbot.innovakickstarter.com/vendor/chatbot/js/external-chatbot.js" data-chatbot-uuid="03afc70e-b94f-450b-8f31-55a7802a37a4" data-iframe-width="420" data-iframe-height="745" data-language="en" ></script>
    --}}

 </body>
</html>