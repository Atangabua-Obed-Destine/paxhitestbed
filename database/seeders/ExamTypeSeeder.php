<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class ExamTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('exam_types')->delete();

        $exam_types = [
            ['title' => 'Final Exam', 'marks' => '100', 'contribution' => '50', 'is_final' => true],
            ['title' => 'Midterm Exam', 'marks' => '50', 'contribution' => '20', 'is_final' => false],
            ['title' => 'Test Exam', 'marks' => '20', 'contribution' => '0', 'is_final' => false],
        ];

        DB::table('exam_types')->insert($exam_types);
    }
}
