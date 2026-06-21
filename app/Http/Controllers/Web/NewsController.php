<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\News;
use App\Models\Language;
use Carbon\Carbon;

class NewsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Newses
        $data['newses'] = News::where('language_id', Language::version()->id)
                            ->where('date', '<=', Carbon::today())
                            ->where('status', '1')
                            ->orderBy('date', 'desc')
                            ->paginate(6);

        return view('web2.news', $data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, $slug)
    {
        // News
        $data['news'] = News::where('id', $id)
                            ->where('status', '1')
                            ->firstOrFail();

        return view('web2.news-single', $data);
    }
}
