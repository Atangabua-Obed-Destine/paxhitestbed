<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EBookCategory;

class EBookCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Computer Science & Technology',
                'slug' => 'computer-science-technology',
                'description' => 'Programming, Software Development, AI, Data Science',
                'icon' => 'fas fa-laptop-code',
                'color' => '#3498db',
                'sort_order' => 1,
            ],
            [
                'name' => 'Mathematics & Statistics',
                'slug' => 'mathematics-statistics',
                'description' => 'Algebra, Calculus, Geometry, Probability',
                'icon' => 'fas fa-calculator',
                'color' => '#e74c3c',
                'sort_order' => 2,
            ],
            [
                'name' => 'Engineering',
                'slug' => 'engineering',
                'description' => 'Civil, Mechanical, Electrical, Chemical Engineering',
                'icon' => 'fas fa-cogs',
                'color' => '#f39c12',
                'sort_order' => 3,
            ],
            [
                'name' => 'Business & Economics',
                'slug' => 'business-economics',
                'description' => 'Management, Finance, Marketing, Economics',
                'icon' => 'fas fa-chart-line',
                'color' => '#2ecc71',
                'sort_order' => 4,
            ],
            [
                'name' => 'Science',
                'slug' => 'science',
                'description' => 'Physics, Chemistry, Biology, Environmental Science',
                'icon' => 'fas fa-flask',
                'color' => '#9b59b6',
                'sort_order' => 5,
            ],
            [
                'name' => 'Literature & Language',
                'slug' => 'literature-language',
                'description' => 'English, Literature, Creative Writing, Linguistics',
                'icon' => 'fas fa-book-open',
                'color' => '#1abc9c',
                'sort_order' => 6,
            ],
            [
                'name' => 'Social Sciences',
                'slug' => 'social-sciences',
                'description' => 'Psychology, Sociology, Anthropology, Political Science',
                'icon' => 'fas fa-users',
                'color' => '#34495e',
                'sort_order' => 7,
            ],
            [
                'name' => 'Medicine & Health',
                'slug' => 'medicine-health',
                'description' => 'Medical, Nursing, Pharmacy, Public Health',
                'icon' => 'fas fa-heartbeat',
                'color' => '#e67e22',
                'sort_order' => 8,
            ],
            [
                'name' => 'Arts & Design',
                'slug' => 'arts-design',
                'description' => 'Graphic Design, Photography, Fine Arts, Architecture',
                'icon' => 'fas fa-palette',
                'color' => '#ff6b9d',
                'sort_order' => 9,
            ],
            [
                'name' => 'History & Geography',
                'slug' => 'history-geography',
                'description' => 'World History, Geography, Archaeology, Cultural Studies',
                'icon' => 'fas fa-globe-africa',
                'color' => '#95a5a6',
                'sort_order' => 10,
            ],
            [
                'name' => 'Law & Legal Studies',
                'slug' => 'law-legal-studies',
                'description' => 'Constitutional Law, Criminal Law, International Law',
                'icon' => 'fas fa-gavel',
                'color' => '#34495e',
                'sort_order' => 11,
            ],
            [
                'name' => 'Education & Teaching',
                'slug' => 'education-teaching',
                'description' => 'Pedagogy, Educational Psychology, Curriculum Development',
                'icon' => 'fas fa-graduation-cap',
                'color' => '#27ae60',
                'sort_order' => 12,
            ],
        ];

        foreach ($categories as $category) {
            EBookCategory::create($category);
        }
    }
}
