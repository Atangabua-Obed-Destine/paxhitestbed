<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebEvent;
use App\Models\Language;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Events
        $data['events'] = WebEvent::where('language_id', Language::version()->id)
                            ->where('status', '1')
                            ->orderBy('date', 'desc')
                            ->paginate(6);

        return view('web2.event', $data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, $slug)
    {
        // Event
        $data['event'] = WebEvent::where('id', $id)
                            ->where('status', '1')
                            ->firstOrFail();

        return view('web2.event-single', $data);
    }
}
