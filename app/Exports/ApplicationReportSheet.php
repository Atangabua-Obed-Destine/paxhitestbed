<?php

namespace App\Exports;

/**
 * One sheet of the admissions board workbook.
 *
 * The formatting — the preamble lines, the bold shaded heading row frozen in
 * view, the filter on every column, and WithStrictNullComparison so a 0 is not
 * written out as a blank — now lives in ReportSheet, which the fees report
 * workbook uses as well. Nothing about the layout changed; it simply has one
 * copy instead of two.
 *
 * The name is kept: ApplicationReportExport builds six of these and reads
 * better for saying what they are.
 */
class ApplicationReportSheet extends ReportSheet
{
}
