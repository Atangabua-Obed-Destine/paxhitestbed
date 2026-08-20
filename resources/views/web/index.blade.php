@extends('web.layouts.master')
@section('title', __('navbar_home'))


@section('social_meta_tags')
    @if(isset($setting))
    <meta property="og:type" content="website">
    <meta property='og:site_name' content="{{ $setting->title }}"/>
    <meta property='og:title' content="{{ $setting->title }}"/>
    <meta property='og:description' content="{!! str_limit(strip_tags($setting->meta_description), 160, ' ...') !!}"/>
    <meta property='og:url' content="{{ route('home') }}"/>
    <meta property='og:image' content="{{ asset('/uploads/setting/'.$setting->logo_path) }}"/>


    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="{!! '@'.str_replace(' ', '', $setting->title) !!}" />
    <meta name="twitter:creator" content="@HiTechParks" />
    <meta name="twitter:url" content="{{ route('home') }}" />
    <meta name="twitter:title" content="{{ $setting->title }}" />
    <meta name="twitter:description" content="{!! str_limit(strip_tags($setting->meta_description), 160, ' ...') !!}" />
    <meta name="twitter:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}" />
    @endif
@endsection

@section('content')

    <!-- main-area -->
    <main>
        <!-- slider-area -->
        <section id="home" class="slider-area fix p-relative">
           
            <div class="slider-active" style="background: #141b22;">

                @foreach($sliders as $slider)
                <div class="single-slider slider-bg" style="background-image: url({{ asset('uploads/slider/'.$slider->attach) }}); background-size: cover;">
                    <div class="overlay"></div>
                    <div class="container">
                       <div class="row">
                            <div class="col-lg-7 col-md-7">
                                <div class="slider-content s-slider-content mt-130">
                                    <h2 data-animation="fadeInUp" data-delay=".4s">{{ $slider->title }}</h2>
                                    <p data-animation="fadeInUp" data-delay=".6s">{!! strip_tags($slider->sub_title, '<b><u><i><br>') !!}</p>
                                    
                                    @if(isset($slider->button_link))
                                    <div class="slider-btn mt-30">     
                                        <a href="{{ $slider->button_link }}" target="_blank" class="btn ss-btn mr-15" data-animation="fadeInLeft" data-delay=".4s">{{ $slider->button_text }} <i class="fal fa-long-arrow-right"></i></a>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-lg-5 col-md-5 p-relative">
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

            </div>
        </section>
        <!-- slider-area-end -->


        @if(count($features) > 0)
        <!-- service-area -->
        <section class="service-details-two p-relative">
            <div class="container">
                <div class="row">
                  
                    @foreach($features as $key => $feature)
                    <div class="col-lg-4 col-md-12 col-sm-12">
                        <div class="services-box07 @if($key == 1) active @endif">
                            <div class="sr-contner">
                                <div class="icon">
                                    <img src="{{ asset('web/img/icon/sve-icon4.png') }}" alt="icon">
                                </div>
                                <div class="text">
                                    <h5>{{ $feature->title }}</h5>
                                    <p>{!! $feature->description !!}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                 
                </div>
            </div>
        </section>
        <!-- service-area-end -->
        @endif
        @isset($welcomeMessage)
        <!-- welcome-message-area -->
        <section class="welcome-message-area pt-120 pb-120 p-relative" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
            <div class="animations-02"><img src="{{ asset('web/img/bg/an-img-02.png') }}" alt="background"></div>
            <div class="container">
                <div class="row align-items-center justify-content-center">
                    <div class="col-lg-10">
                        <div class="welcome-wrapper" style="background: #fff; border-radius: 20px; padding: 60px; box-shadow: 0 20px 60px rgba(0,0,0,0.1);">
                            <div class="row align-items-center">
                                <div class="col-lg-4 col-md-5">
                                    <div class="welcome-image text-center mb-4 mb-md-0 wow fadeInLeft animated" data-animation="fadeInLeft" data-delay=".4s">
                                        @if(isset($welcomeMessage->image) && $welcomeMessage->image && upload_exists('welcome-message/'.$welcomeMessage->image))
                                        <div class="vc-image-wrapper" style="position: relative; display: inline-block;">
                                            <img src="{{ upload_asset('welcome-message/'.$welcomeMessage->image) }}" alt="{{ $welcomeMessage->title }}" class="img-fluid rounded-circle" style="width: 250px; height: 250px; object-fit: cover; border: 8px solid #109bff; box-shadow: 0 15px 45px rgba(16, 155, 255, 0.3);">
                                            <div style="position: absolute; bottom: 10px; right: 10px; width: 60px; height: 60px; background: #109bff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                                                <i class="fas fa-quote-right" style="color: #fff; font-size: 24px;"></i>
                                            </div>
                                        </div>
                                        @else
                                        <div class="vc-image-wrapper" style="position: relative; display: inline-block;">
                                            <img src="{{ asset('web/img/default-avatar.png') }}" alt="{{ $welcomeMessage->title ?? 'Welcome' }}" class="img-fluid rounded-circle" style="width: 250px; height: 250px; object-fit: cover; border: 8px solid #109bff; box-shadow: 0 15px 45px rgba(16, 155, 255, 0.3);">
                                            <div style="position: absolute; bottom: 10px; right: 10px; width: 60px; height: 60px; background: #109bff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                                                <i class="fas fa-quote-right" style="color: #fff; font-size: 24px;"></i>
                                            </div>
                                        </div>
                                        @endif
                                        @if(isset($welcomeMessage->designation))
                                        <div class="vc-designation mt-3">
                                            <h5 style="color: #109bff; font-weight: 700; margin: 0; font-size: 18px;">{{ $welcomeMessage->designation }}</h5>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-lg-8 col-md-7">
                                    <div class="welcome-content wow fadeInRight animated" data-animation="fadeInRight" data-delay=".4s">
                                        <div class="section-title mb-35">
                                            <h5 style="color: #109bff; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px;">
                                                <i class="fas fa-envelope-open-text"></i> Message from Leadership
                                            </h5>
                                            <h2 style="color: #141b22; font-size: 36px; font-weight: 700; line-height: 1.3; margin-bottom: 20px;">
                                                {{ $welcomeMessage->title }}
                                            </h2>
                                            <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #109bff 0%, #1a7a9e 100%); border-radius: 2px;"></div>
                                        </div>
                                        <div class="welcome-text" style="color: #555; font-size: 16px; line-height: 1.8; text-align: justify;">
                                            <div class="welcome-message-content">
                                                {!! $welcomeMessage->message !!}
                                            </div>
                                        </div>
                                        <style>
                                            .welcome-message-content p {
                                                margin-bottom: 15px;
                                                text-align: justify;
                                                word-spacing: normal;
                                            }
                                            
                                            .welcome-message-content ul, 
                                            .welcome-message-content ol {
                                                margin-bottom: 15px;
                                                padding-left: 20px;
                                            }
                                            
                                            .welcome-message-content li {
                                                margin-bottom: 8px;
                                            }
                                            
                                            /* Mobile-specific styles */
                                            @media (max-width: 768px) {
                                                .welcome-text {
                                                    font-size: 15px !important;
                                                    line-height: 1.7 !important;
                                                }
                                                
                                                .welcome-message-content p {
                                                    text-align: left !important;
                                                    word-spacing: normal !important;
                                                    letter-spacing: normal !important;
                                                    margin-bottom: 12px;
                                                }
                                                
                                                .welcome-message-content {
                                                    hyphens: auto;
                                                    -webkit-hyphens: auto;
                                                    word-break: normal;
                                                    overflow-wrap: break-word;
                                                }
                                            }
                                        </style>
                                        <div class="vc-signature mt-4" style="border-top: 2px solid #e9ecef; padding-top: 20px;">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <p style="margin: 0; color: #109bff; font-weight: 600; font-size: 18px;">
                                                        @if(isset($welcomeMessage->designation))
                                                        {{ $welcomeMessage->designation }}
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="col-md-6 text-md-right mt-2 mt-md-0">
                                                    <span style="color: #999; font-size: 14px;">
                                                        <i class="far fa-calendar-alt"></i> {{ now()->format('F Y') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- welcome-message-area-end -->
        @endisset

        <!-- news-area -->
        <section class="blog-area p-relative fix pt-120 pb-120">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="section-title center mb-60" data-aos="fade-up">
                            <h5 style="color: #0066CC; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                                <i class="fas fa-star"></i> NEWS & PRESS RELEASES
                            </h5>
                            <h2 style="font-size: 38px; font-weight: 700; color: #003366; margin-bottom: 15px;">
                                Get the Latest News & Updates on {{ institution_code() }}
                            </h2>
                            <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #0066CC, #FF6B35); border-radius: 2px; margin: 0 auto;"></div>
                        </div>
                    </div>
                </div>
            <div class="container">
                <div class="row">

                    @foreach($newses as $news)
                    <div class="col-lg-4 col-md-6">
                        <div class="single-post2 hover-zoomin mb-30 wow fadeInUp animated" data-animation="fadeInUp" data-delay=".4s">
                            <div class="blog-thumb2">
                                <a href="{{ route('news.single', ['id' => $news->id, 'slug' => $news->slug]) }}"><img src="{{ asset('uploads/news/'.$news->attach) }}" alt="News"></a>

                                <div class="date-home">
                                    <i class="fal fa-calendar-alt"></i> 
                                    {{ date("d F, Y", strtotime($news->date)) }}
                                </div>
                            </div>                    
                            <div class="blog-content2">
                                <h4><a href="{{ route('news.single', ['id' => $news->id, 'slug' => $news->slug]) }}">{{ $news->title }}</a></h4> 

                                <p>{!! str_limit(strip_tags($news->description), 120, ' ...') !!}</p>

                                <div class="blog-btn"><a href="{{ route('news.single', ['id' => $news->id, 'slug' => $news->slug]) }}">{{ __('btn_read_more') }} <i class="fal fa-long-arrow-right"></i></a></div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="pagination-wrap mt-20 text-center">
                            <nav>
                                <ul class="pagination">
                                    {{ $newses->appends(Request::only('search'))->links() }}
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- news-area-end -->


        

        @if(count($testimonials) > 0)
        <!-- testimonial-area -->
        <section hidden class="testimonial-area pt-120 pb-115 p-relative fix">
            <div class="container">
                <div class="row">
                    
                    <div class="col-lg-12">
                        <div class="testimonial-active wow fadeInUp animated" data-animation="fadeInUp" data-delay=".4s">

                            @foreach($testimonials as $testimonial)
                            <div class="single-testimonial text-center">
                                <div class="qt-img">
                                    <img src="{{ asset('web/img/testimonial/qt-icon.png') }}" alt="img">
                                </div>
                                <p>{!! $testimonial->description !!}</p>
                                <div class="testi-author">
                                    <img src="{{ asset('uploads/testimonial/'.$testimonial->attach) }}" alt="img">
                                </div>
                                <div class="ta-info">
                                    <h6>{{ $testimonial->name }}</h6>
                                    <span>{{ $testimonial->designation ?? '' }}</span>
                                </div>                                    
                            </div>
                            @endforeach

                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- testimonial-area-end -->
        @endif
        

        @isset($about)
        <!-- about-area -->
        <section class="about-area about-p pt-120 pb-120 p-relative fix" style="background: #eff7ff;">
            <div class="animations-02"><img src="{{ asset('web/img/bg/an-img-02.png') }}" alt="About"></div>
            <div class="container">
                <div class="row justify-content-center align-items-center">

                    <div class="col-lg-6 col-md-12 col-sm-12">
                        <div class="s-about-img p-relative wow fadeInLeft animated" data-animation="fadeInLeft" data-delay=".4s">
                            <img src="{{ asset('uploads/about-us/'.$about->attach) }}" alt="img">
                            <!-- Stats Badge Overlay -->
                            <div style="position: absolute; bottom: -30px; left: -30px; background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); padding: 30px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 102, 204, 0.3); text-align: center; min-width: 180px;">
                                <h3 style="color: #ffffff; font-size: 48px; font-weight: 700; line-height: 1; margin-bottom: 5px;">20+</h3>
                                <p style="color: rgba(255, 255, 255, 0.9); margin: 0; font-size: 14px;">Years of Excellence</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 col-md-12 col-sm-12">
                        <div class="about-content s-about-content pl-15 wow fadeInRight animated" data-animation="fadeInRight" data-delay=".4s">
                            <div class="about-title second-title pb-25">  
                                <h5><i class="fal fa-graduation-cap"></i> {{ $about->label }}</h5>
                                <h2>{{ $about->title }}</h2>
                                <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #0066CC, #FF6B35); border-radius: 2px; margin-top: 15px;"></div>
                            </div>

                            <div style="font-size: 16px; line-height: 1.8; color: #666666; margin-bottom: 25px;">
                                {!! strip_tags($about->description, '<a><b><i><u><strong><p>') !!}
                            </div>

                            <div class="about-content2">
                                <div class="row">
                                    <div class="col-md-12">
                                        <ul class="green2">
                                            @isset($about->mission_title)
                                            <li>
                                                <div class="abcontent">
                                                    <div class="text">
                                                        <h3>{{ $about->mission_title }}</h3>
                                                        <p>{!! strip_tags($about->mission_desc, '<a><b><i><u><strong>') !!}</p>
                                                    </div>
                                                </div>
                                            </li>
                                            @endisset
                                            @isset($about->vision_title)
                                            <li>
                                                <div class="abcontent">
                                                    <div class="text">
                                                        <h3>{{ $about->vision_title }}</h3>
                                                        <p>{!! strip_tags($about->vision_desc, '<a><b><i><u><strong>') !!}</p>
                                                    </div>
                                                </div>
                                            </li>
                                            @endisset
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Key Features -->
                            <div class="about-features mt-4">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <div class="feature-item d-flex align-items-center">
                                            <i class="fas fa-check-circle mr-2" style="font-size: 20px; color: #28a745 !important;"></i>
                                            <span style="color: #333333; font-weight: 500;">Accredited Programs</span>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="feature-item d-flex align-items-center">
                                            <i class="fas fa-check-circle mr-2" style="font-size: 20px; color: #28a745 !important;"></i>
                                            <span style="color: #333333; font-weight: 500;">Expert Faculty</span>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="feature-item d-flex align-items-center">
                                            <i class="fas fa-check-circle mr-2" style="font-size: 20px; color: #28a745 !important;"></i>
                                            <span style="color: #333333; font-weight: 500;">Modern Facilities</span>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="feature-item d-flex align-items-center">
                                            <i class="fas fa-check-circle mr-2" style="font-size: 20px; color: #28a745 !important;"></i>
                                            <span style="color: #333333; font-weight: 500;">Career Support</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Learn More Button -->
                            <div class="mt-4">
                                <a href="{{ route('about') }}" class="btn" style="background: #0066CC; color: #ffffff !important; padding: 12px 35px; border-radius: 30px; font-weight: 600; box-shadow: 0 8px 25px rgba(0, 102, 204, 0.3); transition: all 0.3s ease; text-decoration: none;">
                                    Learn More About Us <i class="fal fa-long-arrow-right ml-2" style="color: #ffffff !important;"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                 
                </div>
            </div>
        </section>
        <!-- about-area-end -->
        @endisset


        <!-- Why Choose Us Section -->
        <section class="why-choose-us pt-120 pb-90" style="background: #ffffff;">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="section-title center mb-60" data-aos="fade-up">
                            <h5 style="color: #0066CC; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                                <i class="fas fa-star"></i> Why Choose PAX
                            </h5>
                            <h2 style="font-size: 38px; font-weight: 700; color: #003366; margin-bottom: 15px;">
                                What Makes Us Different
                            </h2>
                            <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #0066CC, #FF6B35); border-radius: 2px; margin: 0 auto;"></div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-4 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="100">
                        <div class="feature-box text-center p-4 h-100" style="background: #f8f9fa; border-radius: 15px; transition: all 0.3s ease;">
                            <div class="feature-icon mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #0066CC, #003366); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-certificate" style="font-size: 32px; color: #ffffff !important;"></i>
                            </div>
                            <h4 style="font-size: 20px; font-weight: 700; color: #003366; margin-bottom: 15px;">Accredited Excellence</h4>
                            <p style="color: #666666; line-height: 1.8; margin: 0;">Our programs are internationally recognized and accredited by leading educational bodies.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="200">
                        <div class="feature-box text-center p-4 h-100" style="background: #f8f9fa; border-radius: 15px; transition: all 0.3s ease;">
                            <div class="feature-icon mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #0066CC, #003366); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-users-class" style="font-size: 32px; color: #ffffff !important;"></i>
                            </div>
                            <h4 style="font-size: 20px; font-weight: 700; color: #003366; margin-bottom: 15px;">Expert Faculty</h4>
                            <p style="color: #666666; line-height: 1.8; margin: 0;">Learn from industry professionals and experienced academics dedicated to your success.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="300">
                        <div class="feature-box text-center p-4 h-100" style="background: #f8f9fa; border-radius: 15px; transition: all 0.3s ease;">
                            <div class="feature-icon mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #0066CC, #003366); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-laptop-code" style="font-size: 32px; color: #ffffff !important;"></i>
                            </div>
                            <h4 style="font-size: 20px; font-weight: 700; color: #003366; margin-bottom: 15px;">Modern Facilities</h4>
                            <p style="color: #666666; line-height: 1.8; margin: 0;">State-of-the-art labs, libraries, and technology to enhance your learning experience.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="400">
                        <div class="feature-box text-center p-4 h-100" style="background: #f8f9fa; border-radius: 15px; transition: all 0.3s ease;">
                            <div class="feature-icon mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #0066CC, #003366); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-briefcase" style="font-size: 32px; color: #ffffff !important;"></i>
                            </div>
                            <h4 style="font-size: 20px; font-weight: 700; color: #003366; margin-bottom: 15px;">Career Support</h4>
                            <p style="color: #666666; line-height: 1.8; margin: 0;">Comprehensive career guidance and job placement assistance for all graduates.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="500">
                        <div class="feature-box text-center p-4 h-100" style="background: #f8f9fa; border-radius: 15px; transition: all 0.3s ease;">
                            <div class="feature-icon mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #0066CC, #003366); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-globe-africa" style="font-size: 32px; color: #ffffff !important;"></i>
                            </div>
                            <h4 style="font-size: 20px; font-weight: 700; color: #003366; margin-bottom: 15px;">Global Perspective</h4>
                            <p style="color: #666666; line-height: 1.8; margin: 0;">International partnerships and exchange programs to broaden your horizons.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="600">
                        <div class="feature-box text-center p-4 h-100" style="background: #f8f9fa; border-radius: 15px; transition: all 0.3s ease;">
                            <div class="feature-icon mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #0066CC, #003366); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-hands-helping" style="font-size: 32px; color: #ffffff !important;"></i>
                            </div>
                            <h4 style="font-size: 20px; font-weight: 700; color: #003366; margin-bottom: 15px;">Student Support</h4>
                            <p style="color: #666666; line-height: 1.8; margin: 0;">24/7 academic and personal support services to ensure your success.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Why Choose Us Section End -->


        <!-- Statistics Section -->
        <section hidden class="stats-section" style="background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%); padding: 80px 0; color: #ffffff;">
            <div class="container">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="100">
                        <div class="stat-item">
                            <i class="fas fa-users"></i>
                            <h3 class="counter-home">5000</h3>
                            <p>Students Enrolled</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="200">
                        <div class="stat-item">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <h3 class="counter-home">150</h3>
                            <p>Expert Faculty</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="300">
                        <div class="stat-item">
                            <i class="fas fa-graduation-cap"></i>
                            <h3 class="counter-home">10000</h3>
                            <p>Graduates</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="400">
                        <div class="stat-item">
                            <i class="fas fa-award"></i>
                            <h3 class="counter-home">50</h3>
                            <p>Programs Offered</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Statistics Section End -->

        <!-- Featured Downloads Section -->
        @if(isset($featured_resources) && $featured_resources->count() > 0)
        <section class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%); position: relative; overflow: hidden;">
            <!-- Decorative Background -->
            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"60\" height=\"60\"><circle cx=\"30\" cy=\"30\" r=\"1\" fill=\"rgba(255,255,255,0.03)\"/></svg>'); pointer-events: none;"></div>
            
            <div class="container position-relative" style="z-index: 2;">
                <!-- Section Header -->
                <div class="text-center mb-5" data-aos="fade-up">
                    <span style="display: inline-block; background: linear-gradient(135deg, #0066cc, #7c3aed); padding: 8px 24px; border-radius: 30px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #fff; margin-bottom: 20px;">
                        <i class="fas fa-book-reader me-2"></i> Resources
                    </span>
                    <h2 style="font-size: 42px; font-weight: 800; color: #fff; margin-bottom: 15px;">Essential Downloads</h2>
                    <p style="font-size: 18px; color: rgba(255,255,255,0.6); max-width: 600px; margin: 0 auto;">Access important documents and guides for students and applicants</p>
                </div>

                <!-- Download Cards Grid -->
                <div class="row justify-content-center g-4">
                    @foreach($featured_resources as $resource)
                    <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                        <div style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 30px; text-align: center; transition: all 0.4s ease; height: 100%;" 
                             onmouseover="this.style.transform='translateY(-10px)'; this.style.background='rgba(255,255,255,0.1)'; this.style.boxShadow='0 25px 50px rgba(0,102,204,0.3)';" 
                             onmouseout="this.style.transform='translateY(0)'; this.style.background='rgba(255,255,255,0.05)'; this.style.boxShadow='none';">
                            
                            <!-- Icon Box -->
                            <div style="width: 100px; height: 100px; margin: 0 auto 25px; background: linear-gradient(135deg, #0066cc 0%, #7c3aed 100%); border-radius: 24px; display: flex; align-items: center; justify-content: center; position: relative; box-shadow: 0 15px 40px rgba(0,102,204,0.3);">
                                <i class="{{ $resource->icon ?? $resource->file_icon }}" style="font-size: 40px; color: #fff;"></i>
                                <span style="position: absolute; top: -8px; right: -8px; background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 12px; letter-spacing: 1px;">
                                    {{ strtoupper(pathinfo($resource->file_path, PATHINFO_EXTENSION)) }}
                                </span>
                            </div>

                            <!-- Category Badge -->
                            <span style="display: inline-block; background: rgba(0,102,204,0.2); color: #60a5fa; padding: 5px 15px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">
                                {{ $resource->category_label }}
                            </span>

                            <!-- Title -->
                            <h5 style="color: #fff; font-weight: 700; font-size: 18px; margin-bottom: 10px; line-height: 1.4;">{{ $resource->title }}</h5>

                            @if($resource->description)
                            <p style="color: rgba(255,255,255,0.5); font-size: 14px; margin-bottom: 15px; line-height: 1.6;">{{ Str::limit($resource->description, 60) }}</p>
                            @endif

                            <!-- File Info -->
                            <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
                                <span style="color: rgba(255,255,255,0.5); font-size: 13px;">
                                    <i class="fas fa-weight-hanging" style="color: #60a5fa; margin-right: 5px;"></i> {{ $resource->formatted_file_size }}
                                </span>
                                @if($resource->download_count > 0)
                                <span style="color: rgba(255,255,255,0.5); font-size: 13px;">
                                    <i class="fas fa-download" style="color: #60a5fa; margin-right: 5px;"></i> {{ number_format($resource->download_count) }}
                                </span>
                                @endif
                            </div>

                            <!-- Download Button -->
                            <a href="{{ route('resource.download', $resource->id) }}" 
                               style="display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: #fff; padding: 12px 28px; border-radius: 14px; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; transition: all 0.3s ease;"
                               onmouseover="this.style.background='linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)'; this.style.transform='scale(1.05)';"
                               onmouseout="this.style.background='linear-gradient(135deg, #0066cc 0%, #0052a3 100%)'; this.style.transform='scale(1)';">
                                <span>Download</span>
                                <i class="fas fa-arrow-down"></i>
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Browse All Button -->
                <div class="text-center mt-5" data-aos="fade-up" data-aos-delay="300">
                    <a href="{{ route('resources') }}" 
                       style="display: inline-flex; align-items: center; gap: 12px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; padding: 16px 40px; border-radius: 16px; font-weight: 700; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; box-shadow: 0 15px 40px rgba(245,158,11,0.3); transition: all 0.3s ease;"
                       onmouseover="this.style.transform='translateY(-3px) scale(1.02)'; this.style.boxShadow='0 20px 50px rgba(245,158,11,0.4)';"
                       onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 15px 40px rgba(245,158,11,0.3)';">
                        <i class="fas fa-folder-open"></i>
                        <span>Browse All Resources</span>
                    </a>
                </div>
            </div>
        </section>
        @endif
        <!-- Featured Downloads Section End -->


        @isset($callToAction)
        <!-- cta-area -->
        <section class="cta-area cta-bg pt-50 pb-50" style="background-color: #109bff;">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="section-title cta-title wow fadeInLeft animated" data-animation="fadeInDown animated" data-delay=".2s">
                            <h2>{{ $callToAction->title }}</h2>
                            <p>{{ $callToAction->sub_title }}</p>
                        </div>
                                         
                    </div>
                    <div class="col-lg-4 text-right"> 
                        <div class="cta-btn s-cta-btn wow fadeInRight animated mt-30" data-animation="fadeInDown animated" data-delay=".2s">
                            @if(isset($callToAction->button_link))
                            <a href="{{ $callToAction->button_link }}" target="_blank" class="btn ss-btn smoth-scroll">{{ $callToAction->button_text }} <i class="fal fa-long-arrow-right"></i></a>
                            @endif
                        </div>
                    </div>
                
                </div>
            </div>
        </section>
        <!-- cta-area-end -->
        @endisset


        
     
    </main>
    <!-- main-area-end -->

@endsection

@section('script')
<script>
    // Counter Animation for Home Page
    document.addEventListener('DOMContentLoaded', function() {
        const counters = document.querySelectorAll('.counter-home');
        const speed = 200; // The lower the slower

        const countUp = (counter) => {
            const target = +counter.innerText;
            const count = +counter.getAttribute('data-count') || 0;
            const increment = target / speed;

            if (count < target) {
                counter.setAttribute('data-count', Math.ceil(count + increment));
                counter.innerText = Math.ceil(count + increment);
                setTimeout(() => countUp(counter), 10);
            } else {
                counter.innerText = target.toLocaleString();
            }
        };

        // Intersection Observer for triggering animation when in viewport
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.classList.contains('counted')) {
                    entry.target.classList.add('counted');
                    countUp(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => {
            observer.observe(counter);
        });
    });
</script>
@endsection