<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LetterheadSetting;
use App\Services\LetterheadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The institution's letterhead, configured once and reused by every document.
 */
class LetterheadController extends Controller
{
    protected LetterheadService $letterhead;

    public function __construct(LetterheadService $letterhead)
    {
        $this->letterhead = $letterhead;

        $this->middleware('permission:letterhead-view', ['only' => ['index', 'preview']]);
        $this->middleware('permission:letterhead-edit', ['only' => ['update']]);
    }

    public function index()
    {
        return view('admin.letterhead.index', [
            'title' => __('Letterhead'),
            'row' => LetterheadSetting::current(),
            'tokens' => $this->letterhead->tokens(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:html,reserve_space,none'],
            'html' => ['nullable', 'string'],
            // Beyond about 150mm there would be no page left to print on.
            'reserve_height_mm' => ['nullable', 'integer', 'min:0', 'max:150'],
            'status' => ['nullable', 'boolean'],
        ]);

        $settings = LetterheadSetting::current();
        $settings->fill([
            'mode' => $validated['mode'],
            'html' => $validated['html'] ?? null,
            'reserve_height_mm' => $validated['reserve_height_mm'] ?? 35,
            'status' => $request->boolean('status'),
            'updated_by' => Auth::id(),
        ])->save();

        Flasher::addSuccess(__('Letterhead saved. Every document will use it from now on.'), __('msg_success'));

        return redirect()->route('admin.letterhead.index');
    }

    /**
     * Render it as a real PDF.
     *
     * Deliberately a PDF rather than an HTML preview: the whole point of this
     * screen is that documents come out right when printed or downloaded, and
     * only dompdf can show whether that is true. An HTML preview would have
     * shown the old letterhead image as present while it was silently missing
     * from every generated PDF.
     */
    public function preview()
    {
        $pdf = Pdf::loadView('admin.letterhead.preview', [
            'letterhead' => $this->letterhead->render(true),
            'styles' => $this->letterhead->styles(),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);

        return $pdf->stream('letterhead-preview.pdf');
    }
}
