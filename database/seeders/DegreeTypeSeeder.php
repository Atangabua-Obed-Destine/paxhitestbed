<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DegreeType;

class DegreeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $degreeTypes = [
            [
                'title' => 'Higher National Diploma',
                'shortcode' => 'HND',
                'level' => 'Undergraduate',
                'duration_years' => 2,
                'min_credits' => 120,
                'slug' => 'higher-national-diploma',
                'description' => 'A higher national diploma is a higher education qualification in the United Kingdom. It is equivalent to the second year of a bachelor\'s degree.',
                'requirements' => 'O-Level or equivalent with at least 5 credits including English and Mathematics',
                'sort_order' => 1,
                'status' => 1,
            ],
            [
                'title' => 'Bachelor of Arts',
                'shortcode' => 'BA',
                'level' => 'Undergraduate',
                'duration_years' => 3,
                'min_credits' => 180,
                'slug' => 'bachelor-of-arts',
                'description' => 'Bachelor of Arts degree for humanities and social sciences programs.',
                'requirements' => 'GCE A-Level, Baccalaureate, or equivalent with good grades',
                'sort_order' => 2,
                'status' => 1,
            ],
            [
                'title' => 'Bachelor of Science',
                'shortcode' => 'BSc',
                'level' => 'Undergraduate',
                'duration_years' => 3,
                'min_credits' => 180,
                'slug' => 'bachelor-of-science',
                'description' => 'Bachelor of Science degree for science, technology, and related programs.',
                'requirements' => 'GCE A-Level, Baccalaureate, or equivalent with good grades in science subjects',
                'sort_order' => 3,
                'status' => 1,
            ],
            [
                'title' => 'Bachelor of Technology',
                'shortcode' => 'BTech',
                'level' => 'Undergraduate',
                'duration_years' => 4,
                'min_credits' => 240,
                'slug' => 'bachelor-of-technology',
                'description' => 'Bachelor of Technology degree for engineering and technology programs.',
                'requirements' => 'GCE A-Level with Mathematics and Physics, or HND in related field',
                'sort_order' => 4,
                'status' => 1,
            ],
            [
                'title' => 'Bachelor of Engineering',
                'shortcode' => 'BEng',
                'level' => 'Undergraduate',
                'duration_years' => 4,
                'min_credits' => 240,
                'slug' => 'bachelor-of-engineering',
                'description' => 'Bachelor of Engineering degree for engineering disciplines.',
                'requirements' => 'GCE A-Level with Mathematics and Physics, or equivalent',
                'sort_order' => 5,
                'status' => 1,
            ],
            [
                'title' => 'Postgraduate Diploma',
                'shortcode' => 'PGD',
                'level' => 'Postgraduate',
                'duration_years' => 1,
                'min_credits' => 60,
                'slug' => 'postgraduate-diploma',
                'description' => 'Postgraduate diploma for specialized professional training.',
                'requirements' => 'Bachelor\'s degree from a recognized institution',
                'sort_order' => 6,
                'status' => 1,
            ],
            [
                'title' => 'Master of Arts',
                'shortcode' => 'MA',
                'level' => 'Postgraduate',
                'duration_years' => 2,
                'min_credits' => 120,
                'slug' => 'master-of-arts',
                'description' => 'Master of Arts degree for advanced study in humanities and social sciences.',
                'requirements' => 'Bachelor\'s degree with at least Second Class Lower Division or equivalent',
                'sort_order' => 7,
                'status' => 1,
            ],
            [
                'title' => 'Master of Science',
                'shortcode' => 'MSc',
                'level' => 'Postgraduate',
                'duration_years' => 2,
                'min_credits' => 120,
                'slug' => 'master-of-science',
                'description' => 'Master of Science degree for advanced study in science and technology.',
                'requirements' => 'Bachelor\'s degree with at least Second Class Lower Division or equivalent',
                'sort_order' => 8,
                'status' => 1,
            ],
            [
                'title' => 'Master of Business Administration',
                'shortcode' => 'MBA',
                'level' => 'Postgraduate',
                'duration_years' => 2,
                'min_credits' => 120,
                'slug' => 'master-of-business-administration',
                'description' => 'Master of Business Administration for business management and leadership.',
                'requirements' => 'Bachelor\'s degree and at least 2 years of work experience',
                'sort_order' => 9,
                'status' => 1,
            ],
            [
                'title' => 'Master of Philosophy',
                'shortcode' => 'MPhil',
                'level' => 'Postgraduate',
                'duration_years' => 2,
                'min_credits' => 120,
                'slug' => 'master-of-philosophy',
                'description' => 'Master of Philosophy degree focused on research.',
                'requirements' => 'Bachelor\'s degree with at least Second Class Upper Division',
                'sort_order' => 10,
                'status' => 1,
            ],
            [
                'title' => 'Doctor of Philosophy',
                'shortcode' => 'PhD',
                'level' => 'Postgraduate',
                'duration_years' => 3,
                'min_credits' => 180,
                'slug' => 'doctor-of-philosophy',
                'description' => 'Doctor of Philosophy degree - highest academic degree.',
                'requirements' => 'Master\'s degree with distinction or equivalent research experience',
                'sort_order' => 11,
                'status' => 1,
            ],
            [
                'title' => 'Professional Certificate',
                'shortcode' => 'CERT',
                'level' => 'Certificate',
                'duration_years' => 1,
                'min_credits' => 30,
                'slug' => 'professional-certificate',
                'description' => 'Professional certificate for specialized skills training.',
                'requirements' => 'O-Level or equivalent',
                'sort_order' => 12,
                'status' => 1,
            ],
            [
                'title' => 'Advanced Diploma',
                'shortcode' => 'AdvDip',
                'level' => 'Diploma',
                'duration_years' => 2,
                'min_credits' => 120,
                'slug' => 'advanced-diploma',
                'description' => 'Advanced diploma for professional and technical education.',
                'requirements' => 'O-Level with good grades',
                'sort_order' => 13,
                'status' => 1,
            ],
        ];

        foreach ($degreeTypes as $degreeType) {
            DegreeType::create($degreeType);
        }

        $this->command->info('Degree types seeded successfully!');
    }
}
