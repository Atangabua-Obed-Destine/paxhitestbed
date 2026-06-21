<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Web\News;
use App\Models\Program;
use App\Models\Faculty;
use App\Models\Project;

class SitemapController extends Controller
{
    public function index()
    {
        $currentLanguage = Language::version()->id;

        // Static pages
        $pages = [
            ['url' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['url' => route('about'), 'changefreq' => 'monthly', 'priority' => '0.9'],
            ['url' => route('programs'), 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['url' => route('faculties'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['url' => route('admissions'), 'changefreq' => 'monthly', 'priority' => '0.9'],
            ['url' => route('projects'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['url' => route('campus-life'), 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['url' => route('faq'), 'changefreq' => 'monthly', 'priority' => '0.7'],
        ];

        // Dynamic content
        $news = News::where('language_id', $currentLanguage)
                    ->where('status', '1')
                    ->get(['slug', 'updated_at']);

        $programs = Program::where('is_active', '1')
                          ->get(['slug', 'updated_at']);

        $faculties = Faculty::where('is_active', '1')
                           ->get(['slug', 'updated_at']);

        $projects = Project::where('is_active', '1')
                          ->get(['id', 'updated_at']);

        return response()->view('web.sitemap', [
            'pages' => $pages,
            'news' => $news,
            'programs' => $programs,
            'faculties' => $faculties,
            'projects' => $projects,
        ])->header('Content-Type', 'text/xml');
    }
}
