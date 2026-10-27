{{--
  The four things that help somebody typing a column of up to 88 marks.

  All of it is additive: nothing here validates, saves or submits. The
  existing browser validation on #btnShowConfirmModal and the server-side
  bound in store() are what decide whether a mark is acceptable. This only
  makes the work visible and the typing possible without a mouse.

  Included only when there are rows to mark.
--}}

@push('scripts')
<script>
(function () {
    'use strict';

    var band = document.querySelector('.exm-band');
    var form = document.getElementById('marksForm');

    if (!band || !form) { return; }

    var boxes = Array.prototype.slice.call(
        form.querySelectorAll('input.exm-mark[name^="marks["]')
    ).filter(function (b) { return !b.disabled; });

    var total = parseInt(band.getAttribute('data-exm-total'), 10) || 0;
    var max = parseFloat(band.getAttribute('data-exm-max'));

    /* ---- 1. keyboard flow -------------------------------------------------
       Enter and ArrowDown go to the next student, ArrowUp to the previous, so
       a whole column is typed without reaching for the mouse. Enter is also
       caught here rather than left to the form: this form has no submit
       button, so Enter does nothing today, and moving instead is what a
       marker expects from a column of numbers. */
    boxes.forEach(function (box, i) {
        box.addEventListener('keydown', function (event) {
            var next = null;

            if (event.key === 'Enter' || event.key === 'ArrowDown') {
                next = boxes[i + 1];
            } else if (event.key === 'ArrowUp') {
                next = boxes[i - 1];
            } else {
                return;
            }

            event.preventDefault();

            if (next) {
                next.focus();
                next.select();
            }
        });

        // A wheel over a focused box must never change it. The field is
        // type="text" so it cannot today, but this is the guarantee.
        box.addEventListener('wheel', function (event) {
            if (document.activeElement === box) { event.preventDefault(); }
        }, { passive: false });
    });

    /* ---- 2. progress, distribution and out-of-range, recomputed live ----- */
    var elDone = document.getElementById('exmDone');
    var elBar = document.getElementById('exmBar');
    var elStats = document.getElementById('exmStats');
    var elAvg = document.getElementById('exmAvg');
    var elMin = document.getElementById('exmMin');
    var elMax = document.getElementById('exmMax');
    var elZero = document.getElementById('exmZero');
    var elRange = document.getElementById('exmOutOfRange');

    function recount() {
        var values = [];
        var outOfRange = 0;

        form.querySelectorAll('input.exm-mark[name^="marks["]').forEach(function (box) {
            var raw = (box.value || '').trim();

            if (raw === '') { return; }

            var value = parseFloat(raw);

            if (isNaN(value) || value < 0 || (!isNaN(max) && value > max)) {
                outOfRange++;
                return;
            }

            values.push(value);
        });

        // A locked row is already marked and already counted; it is read from
        // its own box, which is disabled but still carries its value.
        var done = values.length + outOfRange;

        if (elDone) { elDone.textContent = done; }
        if (elBar && total > 0) { elBar.style.width = Math.min(100, done / total * 100) + '%'; }

        if (elStats) { elStats.style.display = values.length ? '' : 'none'; }

        if (values.length) {
            var sum = values.reduce(function (a, b) { return a + b; }, 0);

            if (elAvg) { elAvg.textContent = Math.round(sum / values.length * 10) / 10; }
            if (elMin) { elMin.textContent = Math.round(Math.min.apply(null, values) * 10) / 10; }
            if (elMax) { elMax.textContent = Math.round(Math.max.apply(null, values) * 10) / 10; }
            if (elZero) {
                elZero.textContent = values.filter(function (v) { return v === 0; }).length;
            }
        }

        if (elRange) {
            elRange.textContent = outOfRange
                ? outOfRange + ' ' + elRange.getAttribute('data-label')
                : '';
            elRange.className = outOfRange ? 'exm-count-bad' : 'exm-count-ok';
        }
    }

    form.addEventListener('input', function (event) {
        if (event.target.classList.contains('exm-mark')) { recount(); }
    });

    recount();

    /* ---- 3. do not lose a half-typed column -------------------------------
       Marks LOCK on save, so losing 60 of 88 means unlocking before they can
       be entered again. Armed on the first edit, disarmed on submit. */
    var dirty = false;

    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });

    window.addEventListener('beforeunload', function (event) {
        if (!dirty) { return; }

        // The text is the browser's own; returning a value is what asks.
        event.preventDefault();
        event.returnValue = '';

        return '';
    });
})();
</script>
@endpush
