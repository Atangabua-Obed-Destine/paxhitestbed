@extends('web.layouts.master')
@section('title', __('navbar_faqs'))

@section('social_meta_tags')
    @if(isset($setting))
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $setting->title }}"/>
    <meta property="og:title" content="Frequently Asked Questions | {{ $setting->title }}"/>
    <meta property="og:description" content="Find answers to common questions about PAX Higher Institute, admissions, programs, fees, and student life."/>
    <meta property="og:url" content="{{ route('faq') }}"/>
    <meta property="og:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}"/>

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="{!! '@'.str_replace(' ', '', $setting->title) !!}" />
    <meta name="twitter:url" content="{{ route('faq') }}" />
    <meta name="twitter:title" content="Frequently Asked Questions | {{ $setting->title }}" />
    <meta name="twitter:description" content="Find answers to common questions about PAX Higher Institute, admissions, programs, fees, and student life." />
    <meta name="twitter:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}" />
    
    <!-- Page-Specific Meta Tags -->
    <meta name="description" content="Find answers to common questions about PAX Higher Institute, admissions, programs, fees, and student life.">
    <meta name="keywords" content="FAQ, frequently asked questions, {{ $setting->title }}, admissions FAQ, help, questions, answers">
    <link rel="canonical" href="{{ route('faq') }}">
    @endif
@endsection

@section('structured_data')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [{
        "@type": "ListItem",
        "position": 1,
        "name": "Home",
        "item": "{{ route('home') }}"
    },{
        "@type": "ListItem",
        "position": 2,
        "name": "FAQ",
        "item": "{{ route('faq') }}"
    }]
}
</script>

@if($faqs->count() > 0)
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
        @foreach($faqs as $key => $faq)
        {
            "@type": "Question",
            "name": "{{ $faq->title }}",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "{!! strip_tags($faq->description) !!}"
            }
        }@if(!$loop->last),@endif
        @endforeach
    ]
}
</script>
@endif
@endsection

@section('content')

    <!-- main-area -->
    <main>

        <!-- breadcrumb-area -->
        <section class="breadcrumb-area d-flex  p-relative align-items-center">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-xl-12 col-lg-12">
                        <div class="breadcrumb-wrap text-left">
                            <div class="breadcrumb-title">
                                <h2>{{ __('navbar_faqs') }}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="breadcrumb-wrap2">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('navbar_home') }}</a></li>
                                <li class="breadcrumb-item active" aria-current="page">{{ __('navbar_faqs') }}</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </section>
        <!-- breadcrumb-area-end -->

        <!-- faq-area -->
        <section class="event event03 pt-150 pb-120 p-relative fix">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12 col-md-12  wow fadeInUp animated" data-animation="fadeInUp" data-delay=".4s">
                       <div class="faq-wrap pl-30 wow fadeInUp animated" data-animation="fadeInUp" data-delay=".4s">
                            <div class="accordion" id="accordionExample">

                                @foreach($faqs as $key => $faq)
                                <div class="card">
                                    <div class="card-header" id="heading-{{ $key }}">
                                        <h2 class="mb-0">
                                            <button class="faq-btn @if($key != 0) collapsed @endif" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $key }}">
                                                {{ $faq->title }}
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="collapse-{{ $key }}" class="collapse @if($key == 0) show @endif" data-bs-parent="#accordionExample">
                                        <div class="card-body">
                                            {!! $faq->description !!}
                                        </div>
                                    </div>
                                </div>
                                @endforeach

                            </div>
                        </div>  
                    </div>           
                </div>
            </div>
        </section>
        <!-- faq-area -->
               
    </main>
    <!-- main-area-end -->

@endsection