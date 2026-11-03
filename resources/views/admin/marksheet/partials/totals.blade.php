{{-- The totals and the key to the transcript.

     Kept in one partial because they move together: with an odd number of
     semesters they fill the empty half of the last row rather than starting a
     new band underneath it, and with an even number they follow the marks as
     before. --}}
        {{-- Summary: the totals read after the results they are drawn from.
             Academic standing is left off — it is awarded on graduation, and a
             transcript issued mid-programme should not appear to confer one. --}}
        <div class="tp-summary-bar">
            <div class="tp-summary-item">
                <span class="tp-summary-label">Cumulative GPA</span>
                <span class="tp-summary-value tp-gpa-big">{{ number_format((float)$com_gpa, 2, '.', '') }}</span>
            </div>
            <div class="tp-summary-item">
                <span class="tp-summary-label">Total Credits</span>
                <span class="tp-summary-value">{{ number_format((float)$total_credits, 1) }}</span>
            </div>
            <div class="tp-summary-item">
                <span class="tp-summary-label">Credits Earned</span>
                <span class="tp-summary-value">{{ number_format((float)$total_credits_earned, 1) }}</span>
            </div>
            <div class="tp-summary-item">
                <span class="tp-summary-label">Courses</span>
                <span class="tp-summary-value">{{ $total_courses }}</span>
            </div>
        </div>

        {{-- Key: what every column and abbreviation on this transcript means, so
             it can be read by someone outside the school. --}}
        <div class="tp-key">
            <div class="tp-grade-scale-title">Key to this Transcript</div>
            {{-- Wording kept short on purpose.

                 Every definition that wraps to a second line costs the whole
                 key a row, and the key is competing with the signature block
                 for the bottom of the sheet. Widening to four columns made it
                 WORSE -- narrower columns wrap more -- so the saving comes
                 from the words, not the layout. Each entry says the same thing
                 as before in one line at the printed column width. --}}
            <div class="tp-key-grid">
                <div class="tp-key-item"><span class="tp-key-term">Code</span> Course code in the programme.</div>
                {{-- The three course types get a line each: bundled into one
                     "Type" entry, a reader looking up C or UR did not find them.
                     The wording is the school's own, from the subject form. --}}
                <div class="tp-key-item"><span class="tp-key-term">Type</span> C, UR or E.</div>
                <div class="tp-key-item"><span class="tp-key-term">C</span> Compulsory for this programme.</div>
                <div class="tp-key-item"><span class="tp-key-term">UR</span> University Requirement — for all.</div>
                <div class="tp-key-item"><span class="tp-key-term">E</span> Elective — student's choice.</div>
                <div class="tp-key-item"><span class="tp-key-term">Credit</span> Credit value of the course.</div>
                <div class="tp-key-item"><span class="tp-key-term">Attempted</span> Credits sat for.</div>
                <div class="tp-key-item"><span class="tp-key-term">Earned</span> Credits passed; a fail earns none.</div>
                <div class="tp-key-item"><span class="tp-key-term">Grade Pt</span> Point value, per the Grading Scale.</div>
                <div class="tp-key-item"><span class="tp-key-term">Grade</span> Letter grade awarded.</div>
                <div class="tp-key-item"><span class="tp-key-term">Quality Pts</span> Grade Pt &times; Credit.</div>
                <div class="tp-key-item"><span class="tp-key-term">Semester GPA</span> Quality Pts &divide; credits attempted.</div>
                <div class="tp-key-item"><span class="tp-key-term">Cumulative GPA</span> The same, across all semesters.</div>
                <div class="tp-key-item"><span class="tp-key-term">&mdash;</span> No mark published yet.</div>
            </div>
        </div>
