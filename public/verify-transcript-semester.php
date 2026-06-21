<?php
// Database configuration
$host = 'localhost';
$dbname = 'paxhitest';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<html><head>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #1976d2; }
        h2 { color: #424242; margin-top: 30px; }
        .info-box { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; background: white; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #1976d2; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .success { color: #4caf50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        .grade-A { background-color: #c8e6c9; }
        .grade-F { background-color: #ffcdd2; }
        .summary { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>";
    echo "</head><body>";
    
    $student_id = '12133';
    
    echo "<h1>Transcript Verification for Student $student_id</h1>";
    echo "<h2>October-2025 | Second Semester Y1</h2>";
    
    // Get student info
    echo "<div class='info-box'>";
    echo "<h3>Student Information</h3>";
    $stmt = $pdo->prepare("
        SELECT s.*, b.title as batch_title, p.title as program_title, p.shortcode as program_code
        FROM students s
        LEFT JOIN batches b ON s.batch_id = b.id
        LEFT JOIN programs p ON s.program_id = p.id
        WHERE s.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($student){
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td><strong>Student ID</strong></td><td>{$student['student_id']}</td></tr>";
        echo "<tr><td><strong>Name</strong></td><td>{$student['first_name']} {$student['last_name']}</td></tr>";
        echo "<tr><td><strong>Batch</strong></td><td>{$student['batch_title']}</td></tr>";
        echo "<tr><td><strong>Program</strong></td><td>{$student['program_title']} ({$student['program_code']})</td></tr>";
        echo "</table>";
    } else {
        echo "<p class='error'>Student not found!</p>";
        exit;
    }
    echo "</div>";
    
    // Get all enrollments
    echo "<div class='info-box'>";
    echo "<h3>All Student Enrollments</h3>";
    $stmt = $pdo->prepare("
        SELECT 
            se.id as enroll_id,
            sess.title as session_title,
            sem.title as semester_title,
            sec.title as section_title,
            se.created_at,
            se.status
        FROM student_enrolls se
        LEFT JOIN sessions sess ON se.session_id = sess.id
        LEFT JOIN semesters sem ON se.semester_id = sem.id
        LEFT JOIN sections sec ON se.section_id = sec.id
        WHERE se.student_id = ?
        ORDER BY se.id DESC
    ");
    $stmt->execute([$student['id']]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if($enrollments){
        echo "<table>";
        echo "<tr><th>Enroll ID</th><th>Session</th><th>Semester</th><th>Section</th><th>Created Date</th><th>Status</th></tr>";
        foreach($enrollments as $enroll){
            $status_class = $enroll['status'] == 1 ? 'success' : 'error';
            $status_text = $enroll['status'] == 1 ? 'Active' : 'Inactive';
            echo "<tr>";
            echo "<td>{$enroll['enroll_id']}</td>";
            echo "<td>{$enroll['session_title']}</td>";
            echo "<td>{$enroll['semester_title']}</td>";
            echo "<td>{$enroll['section_title']}</td>";
            echo "<td>{$enroll['created_at']}</td>";
            echo "<td class='$status_class'>$status_text</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>No enrollments found!</p>";
    }
    echo "</div>";
    
    // Get specific semester data
    echo "<div class='info-box'>";
    echo "<h3>October-2025 | Second Semester Y1 Details</h3>";
    
    $stmt = $pdo->prepare("
        SELECT 
            se.id as enroll_id,
            sess.title as session_title,
            sem.title as semester_title,
            sec.title as section_title
        FROM student_enrolls se
        LEFT JOIN sessions sess ON se.session_id = sess.id
        LEFT JOIN semesters sem ON se.semester_id = sem.id
        LEFT JOIN sections sec ON se.section_id = sec.id
        WHERE se.student_id = ?
        AND sess.title = 'October-2025'
        AND sem.title = 'SECOND SEMESTER Y1'
    ");
    $stmt->execute([$student['id']]);
    $target_enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($target_enrollment){
        echo "<p class='success'>✓ Enrollment found for October-2025 | Second Semester Y1</p>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td><strong>Enroll ID</strong></td><td>{$target_enrollment['enroll_id']}</td></tr>";
        echo "<tr><td><strong>Session</strong></td><td>{$target_enrollment['session_title']}</td></tr>";
        echo "<tr><td><strong>Semester</strong></td><td>{$target_enrollment['semester_title']}</td></tr>";
        echo "<tr><td><strong>Section</strong></td><td>{$target_enrollment['section_title']}</td></tr>";
        echo "</table>";
        
        $enroll_id = $target_enrollment['enroll_id'];
        
        // Get subjects for this enrollment
        echo "<h4>Enrolled Subjects</h4>";
        $stmt = $pdo->prepare("
            SELECT 
                s.id as subject_id,
                s.code as subject_code,
                s.title as subject_title,
                s.credit_hour,
                s.subject_type
            FROM student_enroll_subject ses
            JOIN subjects s ON ses.subject_id = s.id
            WHERE ses.student_enroll_id = ?
        ");
        $stmt->execute([$enroll_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if($subjects){
            echo "<table>";
            echo "<tr><th>Subject ID</th><th>Code</th><th>Subject Title</th><th>Credits</th><th>Type</th></tr>";
            foreach($subjects as $subject){
                $type = $subject['subject_type'] == 1 ? 'Compulsory' : ($subject['subject_type'] == 2 ? 'University Requirement' : 'Optional');
                echo "<tr>";
                echo "<td>{$subject['subject_id']}</td>";
                echo "<td>{$subject['subject_code']}</td>";
                echo "<td>{$subject['subject_title']}</td>";
                echo "<td>{$subject['credit_hour']}</td>";
                echo "<td>$type</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Get grades
            $stmt = $pdo->query("SELECT * FROM grades WHERE status = '1' ORDER BY min_mark DESC");
            $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check marks for each subject
            echo "<h4>Marks & Grades Status</h4>";
            echo "<table>";
            echo "<tr><th>Subject</th><th>Total Marks</th><th>Grade</th><th>Point</th><th>Quality Points</th><th>Published</th><th>Publish Date/Time</th></tr>";
            
            $total_quality_points = 0;
            $total_credits = 0;
            $subjects_with_marks = 0;
            
            foreach($subjects as $subject){
                $stmt = $pdo->prepare("
                    SELECT 
                        total_marks,
                        workflow_state,
                        publish_date,
                        publish_time
                    FROM subject_markings
                    WHERE student_enroll_id = ?
                    AND subject_id = ?
                ");
                $stmt->execute([$enroll_id, $subject['subject_id']]);
                $mark = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($mark){
                    $subjects_with_marks++;
                    $marks_per = round($mark['total_marks']);
                    
                    // Find grade
                    $grade_title = 'N/A';
                    $grade_point = 0;
                    $grade_class = '';
                    
                    foreach($grades as $grade){
                        if($marks_per >= $grade['min_mark'] && $marks_per <= $grade['max_mark']){
                            $grade_title = $grade['title'];
                            $grade_point = $grade['point'];
                            $grade_class = $grade_point > 0 ? 'grade-A' : 'grade-F';
                            break;
                        }
                    }
                    
                    $quality_points = $grade_point * $subject['credit_hour'];
                    $total_quality_points += $quality_points;
                    $total_credits += $subject['credit_hour'];
                    
                    $is_published = $mark['workflow_state'] === 'published' ? 'Yes' : 'No';
                    $publish_status = $is_published === 'Yes' ? 'success' : 'warning';
                    
                    // Check if published and visible
                    $now = date('Y-m-d H:i:s');
                    $publish_datetime = $mark['publish_date'] . ' ' . $mark['publish_time'];
                    $is_visible = ($mark['workflow_state'] === 'published' && $publish_datetime <= $now) ? 'Yes' : 'No';
                    
                    echo "<tr class='$grade_class'>";
                    echo "<td>{$subject['subject_code']} - {$subject['subject_title']}</td>";
                    echo "<td>" . number_format($marks_per, 2) . "%</td>";
                    echo "<td><strong>$grade_title</strong></td>";
                    echo "<td>" . number_format($grade_point, 2) . "</td>";
                    echo "<td>" . number_format($quality_points, 2) . "</td>";
                    echo "<td class='$publish_status'><strong>$is_published</strong></td>";
                    echo "<td>$publish_datetime<br><small>Visible: <strong>$is_visible</strong></small></td>";
                    echo "</tr>";
                } else {
                    echo "<tr>";
                    echo "<td>{$subject['subject_code']} - {$subject['subject_title']}</td>";
                    echo "<td colspan='6' class='warning'>No marks recorded</td>";
                    echo "</tr>";
                }
            }
            
            echo "</table>";
            
            // Calculate GPA
            if($total_credits > 0){
                $semester_gpa = $total_quality_points / $total_credits;
                echo "<div class='summary'>";
                echo "<h4>Semester GPA Calculation</h4>";
                echo "<p><strong>Total Quality Points:</strong> " . number_format($total_quality_points, 2) . "</p>";
                echo "<p><strong>Total Credits:</strong> " . number_format($total_credits, 2) . "</p>";
                echo "<p><strong>Semester GPA:</strong> <span style='font-size: 1.5em; color: #1976d2;'>" . number_format($semester_gpa, 2) . "</span></p>";
                echo "<p><strong>Subjects with Marks:</strong> $subjects_with_marks / " . count($subjects) . "</p>";
                echo "</div>";
            } else {
                echo "<p class='warning'>No graded subjects yet to calculate GPA.</p>";
            }
            
        } else {
            echo "<p class='warning'>No subjects enrolled for this semester!</p>";
        }
        
    } else {
        echo "<p class='error'>✗ No enrollment found for October-2025 | SECOND SEMESTER Y1</p>";
        echo "<p>Please check:</p>";
        echo "<ul>";
        echo "<li>Session title is exactly 'October-2025'</li>";
        echo "<li>Semester title is exactly 'SECOND SEMESTER Y1'</li>";
        echo "<li>Enrollment status is active</li>";
        echo "</ul>";
    }
    echo "</div>";
    
    // Show cumulative GPA calculation
    echo "<div class='info-box'>";
    echo "<h3>Cumulative GPA Calculation (All Semesters)</h3>";
    
    $stmt = $pdo->prepare("
        SELECT 
            sess.title as session_title,
            sem.title as semester_title,
            se.id as enroll_id
        FROM student_enrolls se
        LEFT JOIN sessions sess ON se.session_id = sess.id
        LEFT JOIN semesters sem ON se.semester_id = sem.id
        WHERE se.student_id = ?
        AND se.status = 1
        ORDER BY se.id
    ");
    $stmt->execute([$student['id']]);
    $all_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cumulative_quality_points = 0;
    $cumulative_credits = 0;
    
    echo "<table>";
    echo "<tr><th>Semester</th><th>Quality Points</th><th>Credits</th><th>GPA</th></tr>";
    
    foreach($all_enrollments as $enroll){
        $stmt = $pdo->prepare("
            SELECT 
                s.credit_hour,
                sm.total_marks,
                sm.workflow_state,
                sm.publish_date,
                sm.publish_time
            FROM student_enroll_subject ses
            JOIN subjects s ON ses.subject_id = s.id
            LEFT JOIN subject_markings sm ON sm.student_enroll_id = ses.student_enroll_id AND sm.subject_id = s.id
            WHERE ses.student_enroll_id = ?
        ");
        $stmt->execute([$enroll['enroll_id']]);
        $semester_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $semester_qp = 0;
        $semester_cr = 0;
        
        foreach($semester_subjects as $sub){
            if($sub['total_marks'] !== null && $sub['workflow_state'] === 'published'){
                $now = date('Y-m-d H:i:s');
                $publish_datetime = $sub['publish_date'] . ' ' . $sub['publish_time'];
                
                if($publish_datetime <= $now){
                    $marks_per = round($sub['total_marks']);
                    foreach($grades as $grade){
                        if($marks_per >= $grade['min_mark'] && $marks_per <= $grade['max_mark']){
                            $quality_points = $grade['point'] * $sub['credit_hour'];
                            $semester_qp += $quality_points;
                            $semester_cr += $sub['credit_hour'];
                            break;
                        }
                    }
                }
            }
        }
        
        $cumulative_quality_points += $semester_qp;
        $cumulative_credits += $semester_cr;
        
        $sem_gpa = $semester_cr > 0 ? $semester_qp / $semester_cr : 0;
        
        echo "<tr>";
        echo "<td>{$enroll['session_title']} | {$enroll['semester_title']}</td>";
        echo "<td>" . number_format($semester_qp, 2) . "</td>";
        echo "<td>" . number_format($semester_cr, 2) . "</td>";
        echo "<td>" . number_format($sem_gpa, 2) . "</td>";
        echo "</tr>";
    }
    
    $cgpa = $cumulative_credits > 0 ? $cumulative_quality_points / $cumulative_credits : 0;
    
    echo "<tr style='background-color: #e3f2fd; font-weight: bold;'>";
    echo "<td>CUMULATIVE TOTAL</td>";
    echo "<td>" . number_format($cumulative_quality_points, 2) . "</td>";
    echo "<td>" . number_format($cumulative_credits, 2) . "</td>";
    echo "<td style='font-size: 1.2em; color: #1976d2;'>" . number_format($cgpa, 2) . "</td>";
    echo "</tr>";
    echo "</table>";
    echo "</div>";
    
    echo "</body></html>";
    
} catch(PDOException $e) {
    echo "<div class='error'>";
    echo "<h2>Database Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
