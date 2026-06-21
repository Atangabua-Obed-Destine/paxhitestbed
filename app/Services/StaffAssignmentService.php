<?php

namespace App\Services;

use App\Models\AcademicDepartment;
use App\Models\StaffAssignment;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Subject;
use App\User;
use Illuminate\Support\Facades\Auth;

class StaffAssignmentService
{
    /**
     * Check if user is super admin (has access to everything)
     */
    public static function isSuperAdmin($userId = null): bool
    {
        $user = $userId ? User::find($userId) : Auth::user();
        
        if (!$user) {
            return false;
        }
        
        // Check if user has Super Admin role
        return $user->hasRole('Super Admin');
    }
    
    /**
     * Check if user has any staff assignments
     */
    public static function hasAssignments($userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        return StaffAssignment::where('user_id', $userId)->exists();
    }

    /**
     * Check if user is a Head of Department
     */
    public static function isHeadOfDepartment($userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        return AcademicDepartment::where('head_of_department_id', $userId)
            ->where('status', 1)
            ->exists();
    }

    /**
     * Get faculty IDs accessible to the user via their HoD role
     */
    public static function getHodFacultyIds($userId = null): array
    {
        $userId = $userId ?? Auth::id();
        return AcademicDepartment::where('head_of_department_id', $userId)
            ->where('status', 1)
            ->pluck('faculty_id')
            ->unique()
            ->toArray();
    }

    /**
     * Get program IDs accessible to the user via their HoD role
     * (all programs in their academic departments)
     */
    public static function getHodProgramIds($userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $deptIds = AcademicDepartment::where('head_of_department_id', $userId)
            ->where('status', 1)
            ->pluck('id')
            ->toArray();

        if (empty($deptIds)) {
            return [];
        }

        return Program::whereIn('academic_department_id', $deptIds)
            ->pluck('id')
            ->toArray();
    }
    
    /**
     * Get all faculty IDs the user has access to
     */
    public static function getAccessibleFacultyIds($userId = null): array
    {
        $userId = $userId ?? Auth::id();
        
        // Super admin has access to all
        if (self::isSuperAdmin($userId)) {
            return Faculty::pluck('id')->toArray();
        }

        $isHod = self::isHeadOfDepartment($userId);
        $hasAssignments = self::hasAssignments($userId);

        // If no assignments and not HoD, user has default role-based access to all
        if (!$hasAssignments && !$isHod) {
            return Faculty::pluck('id')->toArray();
        }

        $facultyIds = [];

        // Add faculties from staff assignments
        if ($hasAssignments) {
            $facultyIds = StaffAssignment::where('user_id', $userId)
                ->where('assignable_type', Faculty::class)
                ->pluck('assignable_id')
                ->toArray();
        }

        // Add faculties from HoD departments
        if ($isHod) {
            $hodFacultyIds = self::getHodFacultyIds($userId);
            $facultyIds = array_merge($facultyIds, $hodFacultyIds);
        }

        return array_unique($facultyIds);
    }
    
    /**
     * Get all program IDs the user has access to
     */
    public static function getAccessibleProgramIds($userId = null): array
    {
        $userId = $userId ?? Auth::id();
        
        // Super admin has access to all
        if (self::isSuperAdmin($userId)) {
            return Program::pluck('id')->toArray();
        }

        $isHod = self::isHeadOfDepartment($userId);
        $hasAssignments = self::hasAssignments($userId);

        // If no assignments and not HoD, user has default role-based access to all
        if (!$hasAssignments && !$isHod) {
            return Program::pluck('id')->toArray();
        }

        $programIds = [];

        // Add programs from HoD departments
        if ($isHod) {
            $hodProgramIds = self::getHodProgramIds($userId);
            $programIds = array_merge($programIds, $hodProgramIds);
        }

        // Add programs from staff assignments
        if ($hasAssignments) {
            // Get directly assigned programs
            $directPrograms = StaffAssignment::where('user_id', $userId)
                ->where('assignable_type', Program::class)
                ->pluck('assignable_id')
                ->toArray();

            $programIds = array_merge($programIds, $directPrograms);

            // Get programs from assigned faculties (if no specific program assignments for that faculty)
            $assignedFacultyIds = StaffAssignment::where('user_id', $userId)
                ->where('assignable_type', Faculty::class)
                ->pluck('assignable_id')
                ->toArray();

            foreach ($assignedFacultyIds as $facultyId) {
                $hasSpecificPrograms = StaffAssignment::where('user_id', $userId)
                    ->where('assignable_type', Program::class)
                    ->whereIn('assignable_id', function($query) use ($facultyId) {
                        $query->select('id')
                            ->from('programs')
                            ->where('faculty_id', $facultyId);
                    })
                    ->exists();

                if (!$hasSpecificPrograms) {
                    $facultyPrograms = Program::where('faculty_id', $facultyId)->pluck('id')->toArray();
                    $programIds = array_merge($programIds, $facultyPrograms);
                }
            }
        }

        return array_unique($programIds);
    }
    
    /**
     * Get all course/subject IDs the user has access to
     */
    public static function getAccessibleCourseIds($userId = null): array
    {
        $userId = $userId ?? Auth::id();
        
        // Super admin has access to all
        if (self::isSuperAdmin($userId)) {
            return Subject::pluck('id')->toArray();
        }

        // If no assignments and not HoD, user has default role-based access to all
        if (!self::hasAssignments($userId) && !self::isHeadOfDepartment($userId)) {
            return Subject::pluck('id')->toArray();
        }
        
        $courseIds = [];
        
        // Get directly assigned courses
        $directCourses = StaffAssignment::where('user_id', $userId)
            ->where('assignable_type', Subject::class)
            ->pluck('assignable_id')
            ->toArray();
        
        $courseIds = array_merge($courseIds, $directCourses);
        
        // Get courses from assigned programs (if no specific course assignments for that program)
        $assignedProgramIds = self::getAccessibleProgramIds($userId);
        
        foreach ($assignedProgramIds as $programId) {
            // Check if there are specific course assignments for this program
            // Get all subject IDs for this program from the pivot table
            $programSubjectIds = \DB::table('program_subject')
                ->where('program_id', $programId)
                ->pluck('subject_id')
                ->toArray();
            
            $hasSpecificCourses = StaffAssignment::where('user_id', $userId)
                ->where('assignable_type', Subject::class)
                ->whereIn('assignable_id', $programSubjectIds)
                ->exists();
            
            // If no specific courses assigned for this program, include all courses of this program
            if (!$hasSpecificCourses) {
                $courseIds = array_merge($courseIds, $programSubjectIds);
            }
        }
        
        return array_unique($courseIds);
    }
    
    /**
     * Filter faculty query based on user access
     */
    public static function filterFaculties($query, $userId = null)
    {
        $userId = $userId ?? Auth::id();
        
        // Super admin or (no assignments AND not HoD) = no filter
        if (self::isSuperAdmin($userId) || (!self::hasAssignments($userId) && !self::isHeadOfDepartment($userId))) {
            return $query;
        }
        
        $facultyIds = self::getAccessibleFacultyIds($userId);
        return $query->whereIn('id', $facultyIds);
    }
    
    /**
     * Filter program query based on user access
     */
    public static function filterPrograms($query, $userId = null)
    {
        $userId = $userId ?? Auth::id();
        
        // Super admin or (no assignments AND not HoD) = no filter
        if (self::isSuperAdmin($userId) || (!self::hasAssignments($userId) && !self::isHeadOfDepartment($userId))) {
            return $query;
        }
        
        $programIds = self::getAccessibleProgramIds($userId);
        return $query->whereIn('id', $programIds);
    }
    
    /**
     * Filter course/subject query based on user access
     */
    public static function filterCourses($query, $userId = null)
    {
        $userId = $userId ?? Auth::id();
        
        // Super admin or (no assignments AND not HoD) = no filter
        if (self::isSuperAdmin($userId) || (!self::hasAssignments($userId) && !self::isHeadOfDepartment($userId))) {
            return $query;
        }
        
        $courseIds = self::getAccessibleCourseIds($userId);
        return $query->whereIn('id', $courseIds);
    }
    
    /**
     * Check if user has access to a specific faculty
     */
    public static function canAccessFaculty($facultyId, $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        
        if (self::isSuperAdmin($userId) || (!self::hasAssignments($userId) && !self::isHeadOfDepartment($userId))) {
            return true;
        }
        
        return in_array($facultyId, self::getAccessibleFacultyIds($userId));
    }
    
    /**
     * Check if user has access to a specific program
     */
    public static function canAccessProgram($programId, $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        
        if (self::isSuperAdmin($userId) || (!self::hasAssignments($userId) && !self::isHeadOfDepartment($userId))) {
            return true;
        }
        
        return in_array($programId, self::getAccessibleProgramIds($userId));
    }
    
    /**
     * Check if user has access to a specific course
     */
    public static function canAccessCourse($courseId, $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        
        if (self::isSuperAdmin($userId) || (!self::hasAssignments($userId) && !self::isHeadOfDepartment($userId))) {
            return true;
        }
        
        return in_array($courseId, self::getAccessibleCourseIds($userId));
    }
}
