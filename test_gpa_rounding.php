<?php

echo "=== PHP number_format() ROUNDING BEHAVIOR ===\n\n";

echo "The system uses: number_format((float)\$com_gpa, 2, '.', '')\n\n";

echo "=== HOW IT WORKS ===\n";
echo "number_format(\$value, \$decimals, \$decimal_point, \$thousands_separator)\n";
echo "- \$value: The number to format (cast to float)\n";
echo "- \$decimals: 2 (number of decimal places)\n";
echo "- \$decimal_point: '.' (uses period as decimal separator)\n";
echo "- \$thousands_separator: '' (empty, no thousands separator)\n\n";

echo "=== ROUNDING RULE ===\n";
echo "PHP's number_format() uses standard mathematical rounding (round half up):\n";
echo "- If the digit after the decimal place is 5 or greater → round UP\n";
echo "- If the digit after the decimal place is 4 or less → round DOWN\n\n";

echo "=== EXAMPLES ===\n\n";

$test_cases = [
    ['value' => 3.5, 'description' => 'John Doe (PAX25FBF005)'],
    ['value' => 1.909090909, 'description' => 'Deandra Enjoyeh (PAX25MKT004)'],
    ['value' => 3.666666667, 'description' => 'Hypothetical Semester 1 only'],
    ['value' => 3.4999, 'description' => 'Just below 3.5 threshold'],
    ['value' => 3.5001, 'description' => 'Just above 3.5 threshold'],
    ['value' => 2.444, 'description' => 'Rounds down'],
    ['value' => 2.445, 'description' => 'Rounds up'],
    ['value' => 2.4449, 'description' => 'Rounds down (49 < 50)'],
    ['value' => 2.4450, 'description' => 'Rounds up (50 >= 50)'],
    ['value' => 3.495, 'description' => 'Rounds to 3.50'],
    ['value' => 3.494, 'description' => 'Rounds to 3.49'],
];

foreach ($test_cases as $test) {
    $value = $test['value'];
    $rounded = number_format((float)$value, 2, '.', '');
    
    // Show full precision
    printf("Original: %.10f → Rounded: %s (%s)\n", $value, $rounded, $test['description']);
}

echo "\n=== DETAILED STEP-BY-STEP FOR JOHN DOE ===\n\n";

$total_quality_points = 52.5;
$total_credits = 15;

echo "Step 1: Division\n";
$cgpa_unrounded = $total_quality_points / $total_credits;
printf("  \$cgpa = %s ÷ %s = %.15f\n\n", $total_quality_points, $total_credits, $cgpa_unrounded);

echo "Step 2: Cast to Float\n";
$cgpa_float = (float)$cgpa_unrounded;
printf("  (float)\$cgpa = %.15f\n\n", $cgpa_float);

echo "Step 3: Apply number_format() with 2 decimals\n";
$cgpa_formatted = number_format($cgpa_float, 2, '.', '');
printf("  number_format(\$cgpa, 2, '.', '') = %s\n\n", $cgpa_formatted);

echo "Result: {$cgpa_formatted}\n\n";

echo "=== WHY EXACTLY 3.50? ===\n";
echo "52.5 ÷ 15 = 3.5 (exactly, no decimal places beyond .5)\n";
echo "No rounding needed! The value is exactly 3.5000000...\n";
echo "Formatted to 2 decimals: 3.50\n\n";

echo "=== EDGE CASES THAT DEMONSTRATE ROUNDING ===\n\n";

// Demonstrate actual rounding
$edge_cases = [
    ['qp' => 52.49, 'credits' => 15],  // Results in 3.49933... → 3.50
    ['qp' => 52.50, 'credits' => 15],  // Results in 3.50000... → 3.50
    ['qp' => 52.51, 'credits' => 15],  // Results in 3.50066... → 3.50
    ['qp' => 52.44, 'credits' => 15],  // Results in 3.49600... → 3.50
    ['qp' => 52.43, 'credits' => 15],  // Results in 3.49533... → 3.50
    ['qp' => 52.424, 'credits' => 15], // Results in 3.49493... → 3.49
];

foreach ($edge_cases as $case) {
    $qp = $case['qp'];
    $cr = $case['credits'];
    $cgpa = $qp / $cr;
    $rounded = number_format($cgpa, 2, '.', '');
    
    printf("Quality Points: %.2f ÷ Credits: %d = %.10f → %s\n", $qp, $cr, $cgpa, $rounded);
}

echo "\n=== THRESHOLD ANALYSIS ===\n\n";

echo "For a student with 15 credits to get exactly 3.50 CGPA:\n";
echo "- Minimum Quality Points: 3.495 × 15 = 52.425\n";
echo "- Maximum Quality Points: 3.504 × 15 = 52.560\n";
echo "- John Doe has: 52.5 (perfectly in the middle)\n\n";

echo "=== CONCLUSION ===\n";
echo "The system uses PHP's number_format() function which applies\n";
echo "standard mathematical rounding (round half up) to format the\n";
echo "CGPA to exactly 2 decimal places.\n\n";

echo "For John Doe:\n";
echo "- Raw calculation: 52.5 ÷ 15 = 3.5 (exact)\n";
echo "- After rounding: 3.50 (no change, already at 2 decimals)\n";
echo "- Display format: 3.50 ✓\n";
