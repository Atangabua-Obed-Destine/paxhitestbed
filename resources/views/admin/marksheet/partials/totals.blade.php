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
            <div class="tp-key-grid">
                <div class="tp-key-item"><span class="tp-key-term">Code</span> The course code as it appears in the programme.</div>
                {{-- The three course types get a line each: bundled into one
                     "Type" entry, a reader looking up C or UR did not find them.
                     The wording is the school's own, from the subject form. --}}
                <div class="tp-key-item"><span class="tp-key-term">Type</span> What kind of course it is: C, UR or E.</div>
                <div class="tp-key-item"><span class="tp-key-term">C</span> Compulsory — required for this programme.</div>
                <div class="tp-key-item"><span class="tp-key-term">UR</span> University Requirement — required of every student.</div>
                <div class="tp-key-item"><span class="tp-key-term">E</span> Elective — chosen by the student.</div>
                <div class="tp-key-item"><span class="tp-key-term">Credit</span> The credit value of the course.</div>
                <div class="tp-key-item"><span class="tp-key-term">Attempted</span> Credits the student sat for.</div>
                <div class="tp-key-item"><span class="tp-key-term">Earned</span> Credits passed. A failed course earns none.</div>
                <div class="tp-key-item"><span class="tp-key-term">Grade Pt</span> The point value of the grade, from the Grading Scale on this transcript.</div>
                <div class="tp-key-item"><span class="tp-key-term">Grade</span> The letter grade awarded.</div>
                <div class="tp-key-item"><span class="tp-key-term">Quality Pts</span> Grade Pt &times; Credit — what the course contributes to the GPA.</div>
                <div class="tp-key-item"><span class="tp-key-term">Semester GPA</span> Quality Points for the semester &divide; credits attempted in it.</div>
                <div class="tp-key-item"><span class="tp-key-term">Cumulative GPA</span> The same, across every semester on this transcript.</div>
                <div class="tp-key-item"><span class="tp-key-term">&mdash;</span> No mark has been published for that course yet.</div>
            </div>
        </div>
