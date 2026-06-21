<?php
require 'c:/xampp/htdocs/paxhitestbed/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load('c:/xampp/htdocs/paxhitestbed/FBMS-HND First Semester CA &  EXAM Sheet.xlsx');

// Focus on Sheet1 (2) - Frequency Distribution Summary
$sheet = $spreadsheet->getSheetByName('Sheet1 (2)');
if (!$sheet) {
    echo "Sheet not found!\n";
    exit;
}

$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();

echo "=== FREQUENCY DISTRIBUTION SUMMARY (Sheet1 (2)) ===" . PHP_EOL;
echo "Dimensions: " . $highestColumn . $highestRow . " (Rows: $highestRow)" . PHP_EOL . PHP_EOL;

// Read all rows to understand the full structure
echo "=== FULL SHEET CONTENT ===" . PHP_EOL;
for ($row = 1; $row <= $highestRow; $row++) {
    $rowData = [];
    for ($col = 'A'; $col <= 'Z'; $col++) {
        $value = $sheet->getCell($col . $row)->getValue();
        if ($value !== null && $value !== '') {
            $rowData[] = $col . ":" . substr(str_replace(["\n", "\r"], " ", (string)$value), 0, 50);
        }
        if ($col == $highestColumn) break;
    }
    if (!empty($rowData)) {
        echo "Row $row: " . implode(" | ", $rowData) . PHP_EOL;
    }
}

echo PHP_EOL . "=== COLUMN HEADERS ANALYSIS ===" . PHP_EOL;
// Find header row (usually row with most populated cells)
$maxCells = 0;
$headerRow = 1;
for ($row = 1; $row <= 10; $row++) {
    $cellCount = 0;
    for ($col = 'A'; $col <= 'Z'; $col++) {
        $value = $sheet->getCell($col . $row)->getValue();
        if ($value !== null && $value !== '') {
            $cellCount++;
        }
        if ($col == $highestColumn) break;
    }
    if ($cellCount > $maxCells) {
        $maxCells = $cellCount;
        $headerRow = $row;
    }
}

echo "Detected header row: $headerRow with $maxCells columns" . PHP_EOL;
echo "Headers:" . PHP_EOL;
for ($col = 'A'; $col <= 'Z'; $col++) {
    $value = $sheet->getCell($col . $headerRow)->getValue();
    if ($value !== null && $value !== '') {
        echo "  $col: $value" . PHP_EOL;
    }
    if ($col == $highestColumn) break;
}
