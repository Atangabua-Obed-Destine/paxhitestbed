<?php

namespace App\Http\Controllers\Web;

use Illuminate\Support\Facades\Cookie;
use App\Http\Controllers\Controller;
use App\Models\Web\CallToAction;
use App\Models\Web\Testimonial;
use Illuminate\Http\Request;
use App\Models\Web\AboutUs;
use App\Models\Web\Feature;
use App\Models\Web\Slider;
use App\Models\Web\WelcomeMessage;
use App\Models\Web\News;
use App\Models\Language;
use App\Models\Project;
use App\Models\LeadershipTeam;
use App\Models\Accreditation;
use App\Models\HistoryTimeline;
use App\Models\SupportService;
use App\Models\AdmissionDate;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Web\Faq;
use App\Models\Web\Announcement;

class HomeController extends Controller
{   
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Sliders
        $data['sliders'] = Slider::where('language_id', Language::version()->id)
                            ->where('status', '1')
                            ->orderBy('id', 'asc')
                            ->get();

        // Features
        $data['features'] = Feature::where('language_id', Language::version()->id)
                            ->where('status', '1')
                            ->orderBy('id', 'asc')
                            ->get();

        // Welcome Message
        $data['welcomeMessage'] = WelcomeMessage::where('language_id', Language::version()->id)
                            ->where('status', '1')
                            ->orderBy('sort_order', 'asc')
                            ->first();

        // About Us
        $data['about'] = AboutUs::where('language_id', Language::version()->id)
                            ->where('status', '1')
                            ->first();

        // Call To Action
        $data['callToAction'] = CallToAction::where('language_id', Language::version()->id)
                            ->where('status', '1')
                            ->first();

        // Testimonials                                
        $data['testimonials'] = Testimonial::where('language_id', Language::version()->id)
                            ->where('status', '1')
                            ->orderBy('id', 'desc')
                            ->get();

        // News
        $data['newses'] = News::where('language_id', Language::version()->id)
                            ->where('status', '1')
                            ->orderBy('date', 'desc')
                            ->paginate(6);

        // Announcements (active for today and language)
        $today = now()->toDateString();
        $data['announcements'] = Announcement::where('status', 1)
            ->where(function($q) use ($today){
                $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function($q) use ($today){
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->where(function($q){
                $q->where('language_id', Language::version()->id)->orWhereNull('language_id');
            })
            ->orderByDesc('start_date')
            ->get();

        // Featured Resources (Student Guide, Calendarium)
        $data['featured_resources'] = \App\Models\Web\Resource::where('language_id', Language::version()->id)
            ->where('status', 1)
            ->whereIn('category', ['student_guide', 'calendarium'])
            ->orderBy('sort_order', 'asc')
            ->take(4)
            ->get();


        return view('web2.index', $data);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function setCookie(Request $request) {
        //
        if(Cookie::get('sidebar') != 'navbar-collapsed'){
            Cookie::queue(Cookie::make('sidebar', 'navbar-collapsed', 60*60*24*365));
        }
        else{
            Cookie::queue(Cookie::make('sidebar', 'navbar-expeded', 60*60*24*365));
        }

        return response()->json(['data'=> Cookie::get('sidebar')]);
    }

    /**
     * Display About page
     */
    public function about()
    {
        $data['about'] = AboutUs::where('language_id', Language::version()->id)
                            ->where('status', '1')
                            ->first();
        
        $data['leadership'] = LeadershipTeam::where('status', '1')
                            ->orderBy('sort_order', 'asc')
                            ->get();
        
        $data['timeline'] = HistoryTimeline::where('status', '1')
                            ->orderBy('sort_order', 'asc')
                            ->get();
        
        $data['accreditations'] = Accreditation::where('status', '1')
                            ->orderBy('sort_order', 'asc')
                            ->get();

        return view('web2.about', $data);
    }

    /**
     * Display Programs page
     */
    public function programs(Request $request)
    {
        $query = Program::where('status', '1');

        // Filter by faculty
        if ($request->has('faculty') && $request->faculty != '') {
            $query->where('faculty_id', $request->faculty);
        }

        $data['programs'] = $query->with('faculty')
                            ->orderBy('title', 'asc')
                            ->paginate(12);
        
        $data['faculties'] = Faculty::where('status', '1')
                            ->orderBy('title', 'asc')
                            ->get();

        return view('web2.programs', $data);
    }

    /**
     * Display single program detail page
     */
    public function programDetail($slug)
    {
        $data['program'] = Program::where('slug', $slug)
                            ->where('status', '1')
                            ->with('faculty')
                            ->firstOrFail();

        return view('web2.program-single', $data);
    }

    /**
     * Display Faculties page
     */
    public function faculties()
    {
        $data['faculties'] = Faculty::where('status', '1')
                            ->withCount('programs')
                            ->orderBy('title', 'asc')
                            ->get();

        return view('web2.faculties', $data);
    }

    /**
     * Display single faculty detail page
     */
    public function facultyDetail($slug)
    {
        $data['faculty'] = Faculty::where('slug', $slug)
                            ->where('status', '1')
                            ->with('programs')
                            ->firstOrFail();

        return view('web2.faculty-single', $data);
    }

    /**
     * Display Admissions page
     */
    public function admissions()
    {
        $data['admission_dates'] = AdmissionDate::where('status', '1')
                            ->orderBy('sort_order', 'asc')
                            ->orderBy('event_date', 'asc')
                            ->get();

        // Faqs
        $data['faqs'] = Faq::where('language_id', Language::version()->id)
                            ->where('status', '1')
                            ->orderBy('id', 'asc')
                            ->get();

        // Resources for downloads (Student Guide, Calendarium, Forms)
        $data['download_resources'] = \App\Models\Web\Resource::where('language_id', Language::version()->id)
                            ->where('status', 1)
                            ->whereIn('category', ['student_guide', 'calendarium', 'forms', 'academic'])
                            ->orderBy('sort_order', 'asc')
                            ->get();

        return view('web2.admissions', $data);
    }

    /**
     * Display Projects page
     */
    public function projects(Request $request)
    {
        $query = Project::where('is_active', '1');

        // Filter by faculty
        if ($request->has('faculty') && $request->faculty != '') {
            $query->where('faculty_id', $request->faculty);
        }

        // Filter by theme
        if ($request->has('theme') && $request->theme != '') {
            $query->where('theme', $request->theme);
        }

        // Filter by status
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        $data['projects'] = $query->with('faculty')
                            ->orderBy('featured', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->paginate(9);
        
        $data['faculties'] = Faculty::where('status', '1')
                            ->orderBy('title', 'asc')
                            ->get();
        
        // Get unique themes
        $data['themes'] = Project::where('is_active', '1')
                            ->whereNotNull('theme')
                            ->distinct()
                            ->pluck('theme');

        return view('web2.projects', $data);
    }

    /**
     * Display single Project page
     */
    public function projectDetail($id)
    {
        $data['project'] = Project::where('is_active', '1')
                            ->with('faculty')
                            ->findOrFail($id);
        
        // Related projects
        $data['related'] = Project::where('is_active', '1')
                            ->where('id', '!=', $id)
                            ->where(function($query) use ($data) {
                                $query->where('faculty_id', $data['project']->faculty_id)
                                      ->orWhere('theme', $data['project']->theme);
                            })
                            ->limit(3)
                            ->get();

        return view('web2.project-detail', $data);
    }

    /**
     * Display Campus Life page
     */
    public function campusLife()
    {
        $data['services'] = SupportService::where('status', '1')
                            ->orderBy('sort_order', 'asc')
                            ->get();

        // Resources for students (handbooks, policies, etc.)
        $data['student_resources'] = \App\Models\Web\Resource::where('language_id', Language::version()->id)
                            ->where('status', 1)
                            ->whereIn('category', ['student_guide', 'handbook', 'policies'])
                            ->orderBy('sort_order', 'asc')
                            ->take(6)
                            ->get();

        return view('web2.campus-life', $data);
    }
}
