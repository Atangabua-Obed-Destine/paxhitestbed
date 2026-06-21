<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AcademicDepartment;
use App\Models\Faculty;
use Illuminate\Support\Str;

class AcademicDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing faculties
        $faculties = Faculty::all();

        if ($faculties->isEmpty()) {
            echo "No faculties found. Please seed faculties first.\n";
            return;
        }

        $departments = [];

        foreach ($faculties as $faculty) {
            // Example departments based on common academic structures
            // Customize these based on your actual faculty structure
            
            if (stripos($faculty->title, 'Business') !== false || stripos($faculty->title, 'Management') !== false) {
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of Accounting',
                    'shortcode' => 'ACC',
                    'slug' => Str::slug('Department of Accounting'),
                    'description' => 'Department of Accounting and Financial Management',
                    'sort_order' => 1,
                    'status' => 1,
                ];
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of Business Administration',
                    'shortcode' => 'BA',
                    'slug' => Str::slug('Department of Business Administration'),
                    'description' => 'Department of Business Administration and Management',
                    'sort_order' => 2,
                    'status' => 1,
                ];
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of Marketing',
                    'shortcode' => 'MKT',
                    'slug' => Str::slug('Department of Marketing'),
                    'description' => 'Department of Marketing and Sales',
                    'sort_order' => 3,
                    'status' => 1,
                ];
            }
            elseif (stripos($faculty->title, 'Engineering') !== false || stripos($faculty->title, 'Technology') !== false) {
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of Civil Engineering',
                    'shortcode' => 'CE',
                    'slug' => Str::slug('Department of Civil Engineering'),
                    'description' => 'Department of Civil and Structural Engineering',
                    'sort_order' => 1,
                    'status' => 1,
                ];
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of Electrical Engineering',
                    'shortcode' => 'EE',
                    'slug' => Str::slug('Department of Electrical Engineering'),
                    'description' => 'Department of Electrical and Electronics Engineering',
                    'sort_order' => 2,
                    'status' => 1,
                ];
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of Computer Science',
                    'shortcode' => 'CS',
                    'slug' => Str::slug('Department of Computer Science'),
                    'description' => 'Department of Computer Science and Information Technology',
                    'sort_order' => 3,
                    'status' => 1,
                ];
            }
            elseif (stripos($faculty->title, 'Science') !== false) {
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of Mathematics',
                    'shortcode' => 'MATH',
                    'slug' => Str::slug('Department of Mathematics'),
                    'description' => 'Department of Pure and Applied Mathematics',
                    'sort_order' => 1,
                    'status' => 1,
                ];
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of Physics',
                    'shortcode' => 'PHY',
                    'slug' => Str::slug('Department of Physics'),
                    'description' => 'Department of Physics and Applied Physics',
                    'sort_order' => 2,
                    'status' => 1,
                ];
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of Chemistry',
                    'shortcode' => 'CHEM',
                    'slug' => Str::slug('Department of Chemistry'),
                    'description' => 'Department of Chemistry and Biochemistry',
                    'sort_order' => 3,
                    'status' => 1,
                ];
            }
            elseif (stripos($faculty->title, 'Arts') !== false || stripos($faculty->title, 'Humanities') !== false) {
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of English',
                    'shortcode' => 'ENG',
                    'slug' => Str::slug('Department of English'),
                    'description' => 'Department of English Language and Literature',
                    'sort_order' => 1,
                    'status' => 1,
                ];
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of History',
                    'shortcode' => 'HIST',
                    'slug' => Str::slug('Department of History'),
                    'description' => 'Department of History and Archaeology',
                    'sort_order' => 2,
                    'status' => 1,
                ];
            }
            else {
                // Generic departments for any faculty
                $departments[] = [
                    'faculty_id' => $faculty->id,
                    'title' => 'Department of General Studies',
                    'shortcode' => 'GS',
                    'slug' => Str::slug('Department of General Studies') . '-' . $faculty->id,
                    'description' => 'Department of General Studies',
                    'sort_order' => 1,
                    'status' => 1,
                ];
            }
        }

        // Insert all departments
        foreach ($departments as $department) {
            AcademicDepartment::create($department);
        }

        echo "Academic departments seeded successfully!\n";
    }
}
