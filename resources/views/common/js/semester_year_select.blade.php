<!-- Year -> Semester: one implementation, used by every semester picker. -->
<script type="text/javascript">
(function () {
  "use strict";

  // Loaded from the layout footer, so a page that also includes it directly
  // does not get a second copy. The flag is set here rather than at the end,
  // because when jQuery has not loaded yet this defers to the load event and a
  // second copy would otherwise slip past and queue its own.
  if (window.__semesterYearSelectLoaded) {
    return;
  }
  window.__semesterYearSelectLoaded = true;

  var boot = function () {
    if (typeof window.jQuery === 'undefined') {
      window.addEventListener('load', boot, { once: true });
      return;
    }

    var $ = window.jQuery;

    // `semesters.year` is a nullable VARCHAR and is not cast on the model, so
    // it arrives as "1", not 1. Every comparison in here goes through this.
    // parseInt would look equivalent and is not: it turns a missing year into
    // NaN, and NaN never equals NaN, so such a semester would quietly vanish
    // from every list with nothing to show it had.
    var same = function (a, b) {
      return String(a) === String(b);
    };

    // Numerically: year 10 belongs after year 9, not between 1 and 2.
    var sortYears = function (keys) {
      return keys.slice().sort(function (a, b) {
        return Number(a) - Number(b);
      });
    };

    var resetDropdown = function ($element, placeholder, includeAll, allLabel) {
      if (!$element || !$element.length) {
        return;
      }
      $element.empty();
      $element.append($('<option/>', { value: '', text: placeholder }));
      if (includeAll) {
        $element.append($('<option/>', { value: '0', text: allLabel }));
      }
    };

    /**
     * Group the flat `filter-semester` response into { year: [{id, title}] }.
     *
     * The endpoint already returns the full model including `year`, so the
     * grouping happens here and the server needs no change.
     */
    var groupByYear = function (rows) {
      var grouped = {};

      if (Array.isArray(rows)) {
        rows.forEach(function (item) {
          if (item && item.year !== null && item.year !== undefined && item.year !== '') {
            var key = String(item.year);
            if (!grouped[key]) {
              grouped[key] = [];
            }
            grouped[key].push({ id: item.id, title: item.title });
          }
        });

        Object.keys(grouped).forEach(function (key) {
          grouped[key].sort(function (a, b) {
            return Number(a.id) - Number(b.id);
          });
        });
      }

      return grouped;
    };

    /**
     * Which year holds this semester?
     *
     * This is what keeps a bookmarked URL working: the link carries only
     * `?semester=2`, so the year it belongs to has to be worked back out before
     * the gated semester list can show it as selected.
     */
    var deriveYear = function (lookup, semesterId) {
      if (!semesterId) {
        return '';
      }

      var derived = '';

      Object.keys(lookup).some(function (yearKey) {
        var hit = lookup[yearKey].some(function (item) {
          return same(item.id, semesterId);
        });
        if (hit) {
          derived = yearKey;
          return true;
        }
        return false;
      });

      return derived;
    };

    /**
     * Wire a Year select to a Semester select.
     *
     * Returns a handle so the caller's own programme-change AJAX can hand over
     * a fresh list without knowing how any of this works.
     */
    var attach = function (options) {
      var opts = options || {};
      var $year = opts.$year;
      var $semester = opts.$semester;

      if (!$year || !$year.length || !$semester || !$semester.length) {
        return null;
      }

      var selectLabel = opts.selectLabel || 'Select';
      var yearLabel = opts.yearLabel || 'Year';
      var allLabel = opts.allLabel || 'All';
      var allowSemesterAll = !!opts.includeSemesterAll;
      var allowYearAll = !!opts.includeYearAll;
      var lookup = opts.lookup || {};
      var selectedYear = opts.selectedYear ? String(opts.selectedYear) : '';
      var selectedSemester = opts.selectedSemester ? String(opts.selectedSemester) : '';

      var buildYears = function (keepYear) {
        resetDropdown($year, selectLabel, allowYearAll, allLabel);

        sortYears(Object.keys(lookup)).forEach(function (yearKey) {
          var option = $('<option/>', { value: yearKey, text: yearLabel + ' ' + yearKey });
          if (keepYear && same(keepYear, yearKey)) {
            option.attr('selected', 'selected');
          }
          $year.append(option);
        });

        // A semester with no year would be unreachable once the list is gated,
        // so it is offered under its own heading - but only when one exists,
        // so clean data never sees it.
        if (Array.isArray(opts.orphans) && opts.orphans.length) {
          var orphanOption = $('<option/>', {
            value: '__none__',
            text: opts.orphanLabel || '— (year not set)'
          });
          if (keepYear === '__none__') {
            orphanOption.attr('selected', 'selected');
          }
          $year.append(orphanOption);
        }
      };

      var buildSemesters = function (yearKey, keepSemester) {
        resetDropdown($semester, selectLabel, allowSemesterAll, allLabel);

        var items = [];

        if (yearKey === '0' && allowYearAll) {
          // "All years" flattens the lot, labelled so the rows stay tellable
          // apart - this is what keeps the all-semesters reports working.
          sortYears(Object.keys(lookup)).forEach(function (key) {
            lookup[key].forEach(function (item) {
              items.push({ id: item.id, title: yearLabel + ' ' + key + ' — ' + item.title });
            });
          });
        } else if (yearKey === '__none__') {
          items = (opts.orphans || []).slice();
        } else if (yearKey && lookup[yearKey]) {
          items = lookup[yearKey].slice();
        }

        items.forEach(function (item) {
          var option = $('<option/>', { value: item.id, text: item.title });
          if (keepSemester && same(keepSemester, item.id)) {
            option.attr('selected', 'selected');
          }
          $semester.append(option);
        });

        if (allowSemesterAll && keepSemester === '0') {
          $semester.val('0');
        }
      };

      var render = function () {
        // Work the year back out of a semester that arrived without one.
        if (!selectedYear && selectedSemester && selectedSemester !== '0') {
          selectedYear = deriveYear(lookup, selectedSemester);
        }

        var yearKeys = Object.keys(lookup);

        // Exactly one year is not a choice, so make it for them - but leave the
        // control enabled, so what was chosen stays visible.
        if (!selectedYear && yearKeys.length === 1 && !allowYearAll) {
          selectedYear = String(yearKeys[0]);
        }

        buildYears(selectedYear);
        if (selectedYear) {
          $year.val(selectedYear);
        }
        buildSemesters(selectedYear, selectedSemester);
      };

      $year.off('change.semesterYear').on('change.semesterYear', function () {
        selectedYear = $(this).val() || '';
        selectedSemester = '';
        buildSemesters(selectedYear, '');
        if (typeof opts.onYearChange === 'function') {
          opts.onYearChange(selectedYear);
        }
        $semester.trigger('change');
      });

      render();

      return {
        // Hand over a fresh list - the flat endpoint response, or an
        // already-grouped map.
        setLookup: function (rows, keepSemester) {
          lookup = Array.isArray(rows) ? groupByYear(rows) : (rows || {});
          if (Array.isArray(rows)) {
            opts.orphans = rows.filter(function (item) {
              return item && (item.year === null || item.year === undefined || item.year === '');
            }).map(function (item) {
              return { id: item.id, title: item.title };
            });
          }
          selectedSemester = keepSemester ? String(keepSemester) : '';
          selectedYear = '';
          render();
        },
        year: function () {
          return selectedYear;
        },
        hasYears: function () {
          return Object.keys(lookup).length > 0;
        }
      };
    };

    /**
     * The per-course card shape: a Year select beside a Semester select, one
     * pair per card, tied together by data-unique.
     *
     * Unlike the filter bars these do not gate - a card is already scoped to a
     * single course, so its list is one to three entries and both selects
     * arrive pre-chosen. The year only has to narrow what is already short.
     *
     * The options are rebuilt rather than hidden. Hiding an <option> does
     * nothing at all in Safari and on iOS, so the hidden semesters stayed
     * selectable there - the handler this replaces looked like it worked and
     * did not, on exactly the phones a lecturer marks from.
     */
    var bindCards = function (options) {
      var opts = options || {};
      var yearSelector = opts.yearSelector || '.quick-year';
      var semesterPrefix = opts.semesterPrefix || '#semester_';

      var repaint = function ($yearSelect) {
        var unique = $yearSelect.data('unique');
        var $semester = $(semesterPrefix + unique);

        if (!$semester.length) {
          return;
        }

        var all = $semester.data('semesters');
        if (!all || !Array.isArray(all)) {
          return;
        }

        var wanted = $yearSelect.val();
        var previous = $semester.val();

        $semester.empty();

        all.forEach(function (item) {
          if (!same(item.year, wanted)) {
            return;
          }
          var isResit = item.is_resit === true || item.is_resit === 1 || item.is_resit === '1';
          $semester.append($('<option/>', {
            value: item.id,
            text: item.title + (isResit ? ' (Resit)' : ''),
            'data-year': item.year,
            'data-is-resit': isResit ? '1' : '0'
          }));
        });

        // Keep what was chosen if it survived the change of year; otherwise
        // fall to the first of the new list, so the card is never left with
        // nothing selected and its action button working on a stale id.
        if (previous && $semester.find('option[value="' + previous + '"]').length) {
          $semester.val(previous);
        } else {
          $semester.val($semester.find('option:first').val() || '');
        }

        $semester.trigger('change');
      };

      $(document)
        .off('change.semesterYearCards', yearSelector)
        .on('change.semesterYearCards', yearSelector, function () {
          repaint($(this));
        });

      $(yearSelector).each(function () {
        repaint($(this));
      });
    };

    window.SemesterYearSelect = {
      groupByYear: groupByYear,
      deriveYear: deriveYear,
      attach: attach,
      bindCards: bindCards,
      sortYears: sortYears
    };

    $(document).trigger('semester-year-select:ready');
  };

  boot();
})();
</script>
