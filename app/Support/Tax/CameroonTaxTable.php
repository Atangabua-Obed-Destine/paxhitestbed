<?php

namespace App\Support\Tax;

/**
 * The payroll taxes a Cameroonian school withholds, as one table.
 *
 * WHERE THESE NUMBERS COME FROM. The DGI's "Barème indicatif de liquidation de
 * l'IRPP et autres retenues salariales, hors taxe de développement local" is a
 * lookup table in 250 FCFA steps of gross monthly pay, 52 000 to 3 097 000,
 * printed over 70 pages. It is not data, though: it is the statutory
 * computation rendered row by row --
 *
 *      CNPS        = min(gross x 4.2%, 75 000)        employee share, capped
 *      net taxable = gross x 70% - CNPS - 500 000/12  30% allowance, abatement
 *      IRPP        = 10 / 15 / 25 / 35% on the annualised net, by band
 *
 * Below the CNPS cap the net taxable is gross x (0.70 - 0.042) = gross x
 * 0.658, so WITHIN A BAND the tax is linear in gross pay:
 *
 *      IRPP = (gross - threshold) x marginal
 *
 * and every marginal is a statutory rate times the net factor:
 *
 *      6.58 = 10 x 0.658      16.45 = 25 x 0.658      24.50 = 35 x 0.70
 *      9.87 = 15 x 0.658      23.03 = 35 x 0.658
 *
 * That shape -- a rate on pay above a threshold -- is exactly one tax_settings
 * row, as percentange with max_no_taxable_amount, and TaxGroup::calculateTax()
 * already evaluates it. So five rows stand in for 12 276, with no new engine
 * and no schema change.
 *
 * VERIFIED, not asserted. The five brackets were checked against 11 972 rows
 * parsed independently out of the PDF: 98.18% reproduce exactly and 100% fall
 * within one franc, which is the rounding the printed table itself carries.
 *
 * WHY THIS CLASS EXISTS. The same table is needed in two places -- the seeder
 * that configures a new school, and the command that corrects an existing one
 * -- and the tax module's own history is a warning about what happens when one
 * rule lives in several places: PayrollTaxBreakdownService's docblock lists
 * four implementations of the bracket rules that had to be hand-synchronised,
 * and there turned out to be six. One copy, read by both.
 */
class CameroonTaxTable
{
    /**
     * The lowest gross pay the barème covers.
     *
     * Its first row is 52 000, and that row already carries CCF 520 and CRTV
     * 750 -- so both bite below the income-tax threshold. What is due under
     * 52 000 the document does not say, so nothing is charged there rather
     * than a figure being assumed.
     */
    public const FLOOR = 52000;

    /** Gross pay at which the social contribution stops growing. */
    public const CNPS_CAP_BASE = 1785715;
    public const CNPS_CAP_AMOUNT = 75000;
    public const CNPS_RATE = 4.2;

    /** Employer shares, as the barème's own columns give them. */
    public const CFC_EMPLOYEE_RATE = 1.0;
    public const CFC_EMPLOYER_RATE = 1.5;
    public const FNE_EMPLOYER_RATE = 1.0;

    /** The council surcharge, as a percentage of the income tax it follows. */
    public const CAC_RATE = 10.0;

    /** An upper bound for a bracket that is open-ended in practice. */
    public const OPEN_TOP = 50000000;

    /**
     * Personal income tax, as five brackets.
     *
     * `percent` is the marginal rate on pay above `threshold`. `note` records
     * which statutory rate each one is, so a future reader can re-derive it
     * rather than having to trust the table.
     */
    public const INCOME_TAX = [
        ['order' => 1, 'min' => 0,       'max' => 316616,   'percent' => 6.58,  'threshold' => 63323.20,  'note' => '10% statutory x 0.658'],
        ['order' => 2, 'min' => 316617,  'max' => 443262,   'percent' => 9.87,  'threshold' => 147754.14, 'note' => '15% statutory x 0.658'],
        ['order' => 3, 'min' => 443263,  'max' => 696555,   'percent' => 16.45, 'threshold' => 265957.45, 'note' => '25% statutory x 0.658'],
        ['order' => 4, 'min' => 696556,  'max' => 1785714,  'percent' => 23.03, 'threshold' => 388985.38, 'note' => '35% statutory x 0.658'],
        ['order' => 5, 'min' => 1785715, 'max' => self::OPEN_TOP, 'percent' => 24.50, 'threshold' => 472789.12, 'note' => '35% statutory x 0.70, past the CNPS cap'],
    ];

    /**
     * Audio-visual tax (CRTV): a flat amount per 100 000 of pay.
     *
     * Read straight off the barème's CRTV column. The boundary sits at every
     * round 100 000 and the amount rises by 1 300 each time, except the first
     * step (1 200) and the last band, which is flat at 13 000.
     */
    public const AUDIO_VISUAL = [
        ['order' => 1,  'min' => self::FLOOR, 'max' => 100000,  'amount' => 750],
        ['order' => 2,  'min' => 100250,      'max' => 200000,  'amount' => 1950],
        ['order' => 3,  'min' => 200250,      'max' => 300000,  'amount' => 3250],
        ['order' => 4,  'min' => 300250,      'max' => 400000,  'amount' => 4550],
        ['order' => 5,  'min' => 400250,      'max' => 500000,  'amount' => 5850],
        ['order' => 6,  'min' => 500250,      'max' => 600000,  'amount' => 7150],
        ['order' => 7,  'min' => 600250,      'max' => 700000,  'amount' => 8450],
        ['order' => 8,  'min' => 700250,      'max' => 800000,  'amount' => 9750],
        ['order' => 9,  'min' => 800250,      'max' => 900000,  'amount' => 11050],
        ['order' => 10, 'min' => 900250,      'max' => 1000000, 'amount' => 12350],
        ['order' => 11, 'min' => 1000250,     'max' => self::OPEN_TOP, 'amount' => 13000],
    ];

    /**
     * Local development tax (TDL): a flat amount per band.
     *
     * NOT from the barème — that document excludes it by its own title, "hors
     * taxe de développement local". These are the bands PAX HIGHER INSTITUTE
     * was already using: contiguous from zero, well-formed, and consistent
     * with the published TDL schedule as far as it goes. They have NOT been
     * checked against that schedule line by line, which is the one thing in
     * this file that rests on the institution rather than on a document.
     *
     * Seeded anyway, because a school with no TDL configured under-withholds
     * from every member of staff, and that is the worse failure. Confirm them
     * with your accountant.
     */
    public const LOCAL_DEVELOPMENT = [
        ['order' => 1,  'min' => 0,      'max' => 60000,   'amount' => 0],
        ['order' => 2,  'min' => 62000,  'max' => 75000,   'amount' => 250],
        ['order' => 3,  'min' => 75001,  'max' => 100000,  'amount' => 500],
        ['order' => 4,  'min' => 100001, 'max' => 125000,  'amount' => 750],
        ['order' => 5,  'min' => 125001, 'max' => 150000,  'amount' => 1000],
        ['order' => 6,  'min' => 150001, 'max' => 200000,  'amount' => 1250],
        ['order' => 7,  'min' => 200001, 'max' => 250000,  'amount' => 1500],
        ['order' => 8,  'min' => 250001, 'max' => 300000,  'amount' => 2000],
        ['order' => 9,  'min' => 300001, 'max' => 500000,  'amount' => 2250],
        ['order' => 10, 'min' => 500001, 'max' => self::OPEN_TOP, 'amount' => 2500],
    ];

    /**
     * The group codes this table configures, and what each one is.
     *
     * Matched on `code` rather than title: a school may rename a group in its
     * own words, and the code is what the configuration keys on.
     */
    public const GROUPS = [
        'PIT' => 'Personal Income Tax',
        'LDT' => 'Local Development Tax',
        'HLF' => 'Housing Loan Fund',
        'CRTV' => 'Audio Visual Tax',
        'IA' => 'Industrial Accident',
    ];

    /**
     * Points taken off the printed table, for checking a configuration against
     * it. Chosen to sit inside every one of the five income-tax brackets and
     * immediately either side of all four boundaries, which is where a wrong
     * rate or a wrong threshold shows up first.
     *
     * @return array<int, int> gross monthly pay => income tax due
     */
    public static function checkpoints(): array
    {
        return [
            63250 => 0,            // below the threshold: nothing is due
            63500 => 12,           // the first franc of tax
            90000 => 1755,
            100000 => 2413,
            110000 => 3071,
            120000 => 3729,
            170000 => 7019,
            180000 => 7677,
            316500 => 16659,       // bracket 1, just below the edge
            316750 => 16680,       // bracket 2, just above it
            443250 => 29165,       // bracket 2, top
            500000 => 38500,       // bracket 3
            696500 => 70824,       // bracket 3, top
            1000000 => 140717,     // bracket 4
            1785750 => 321675,     // bracket 5, just past the social ceiling
            3000000 => 619167,     // bracket 5, near the top of the table
        ];
    }

    /**
     * Which groups are genuinely progressive, since the payslip prints it.
     *
     * True of the income tax: each band carries its own marginal rate with a
     * threshold chosen so the charge is continuous across the boundary, which
     * reproduces the statutory progressive tax exactly. Not true of a flat
     * amount per band, or of one rate on the whole of pay -- and a payslip
     * calling those progressive is a claim a member of staff could query.
     *
     * @return array<string, bool> group key => is it progressive
     */
    public static function progressive(): array
    {
        return [
            'income_tax' => true,
            'audio_visual' => false,
            'housing_fund' => false,
            'local_development' => false,
        ];
    }
}
