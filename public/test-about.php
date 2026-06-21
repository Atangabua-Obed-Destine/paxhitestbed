<?php
/**
 * Test About Page Data
 * Visit: http://localhost/paxhitest/test-about.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Web\AboutUs;
use App\Models\LeadershipTeam;
use App\Models\Accreditation;
use App\Models\HistoryTimeline;
use App\Models\Language;

?>
<!DOCTYPE html>
<html>
<head>
    <title>About Page Data Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #0066CC; border-bottom: 2px solid #FF6B35; padding-bottom: 10px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: #0066CC; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table td { padding: 8px; border-bottom: 1px solid #eee; }
        table td:first-child { font-weight: bold; width: 200px; }
    </style>
</head>
<body>
    <h1>📊 About Page Data Test</h1>
    <p>Testing data availability for <strong>http://localhost/paxhitest/about</strong></p>
    
    <div class="card">
        <h2>1️⃣ About Us Data</h2>
        <?php
        try {
            $about = AboutUs::where('language_id', Language::version()->id)
                        ->where('status', '1')
                        ->first();
            
            if ($about) {
                echo '<p class="success">✅ About Us record found!</p>';
                echo '<table>';
                echo '<tr><td>ID:</td><td>' . $about->id . '</td></tr>';
                echo '<tr><td>Title:</td><td>' . $about->title . '</td></tr>';
                echo '<tr><td>Label:</td><td>' . $about->label . '</td></tr>';
                echo '<tr><td>Has Image:</td><td>' . ($about->attach ? '✅ Yes (' . $about->attach . ')' : '❌ No') . '</td></tr>';
                echo '<tr><td>Mission Title:</td><td>' . ($about->mission_title ?? 'N/A') . '</td></tr>';
                echo '<tr><td>Vision Title:</td><td>' . ($about->vision_title ?? 'N/A') . '</td></tr>';
                echo '<tr><td>Status:</td><td>' . ($about->status == 1 ? '✅ Active' : '❌ Inactive') . '</td></tr>';
                echo '</table>';
            } else {
                echo '<p class="error">❌ No About Us record found!</p>';
                echo '<p class="info">Please add "About Us" content via admin panel: <a href="http://localhost/paxhitest/admin/web/about-us">Click here</a></p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="card">
        <h2>2️⃣ Leadership Team</h2>
        <?php
        try {
            $leadership = LeadershipTeam::where('status', '1')->get();
            echo '<p class="info">Count: <strong>' . $leadership->count() . '</strong></p>';
            
            if ($leadership->count() > 0) {
                echo '<p class="success">✅ Leadership team members found!</p>';
                echo '<ul>';
                foreach ($leadership as $member) {
                    echo '<li>' . $member->name . ' - ' . $member->designation . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="error">❌ No leadership team members found!</p>';
                echo '<p class="info">Add via: <a href="http://localhost/paxhitest/admin/web/leadership-team">Leadership Team Admin</a></p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="card">
        <h2>3️⃣ History Timeline</h2>
        <?php
        try {
            $timeline = HistoryTimeline::where('status', '1')->get();
            echo '<p class="info">Count: <strong>' . $timeline->count() . '</strong></p>';
            
            if ($timeline->count() > 0) {
                echo '<p class="success">✅ Timeline events found!</p>';
                echo '<ul>';
                foreach ($timeline as $event) {
                    echo '<li>' . $event->year . ' - ' . $event->title . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="error">❌ No timeline events found!</p>';
                echo '<p class="info">Add via: <a href="http://localhost/paxhitest/admin/web/history-timeline">History Timeline Admin</a></p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="card">
        <h2>4️⃣ Accreditations</h2>
        <?php
        try {
            $accreditations = Accreditation::where('status', '1')->get();
            echo '<p class="info">Count: <strong>' . $accreditations->count() . '</strong></p>';
            
            if ($accreditations->count() > 0) {
                echo '<p class="success">✅ Accreditations found!</p>';
                echo '<ul>';
                foreach ($accreditations as $accreditation) {
                    echo '<li>' . $accreditation->name . ' - ' . $accreditation->organization . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="error">❌ No accreditations found!</p>';
                echo '<p class="info">Add via: <a href="http://localhost/paxhitest/admin/web/accreditation">Accreditations Admin</a></p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <hr>
    <div class="card">
        <h2>✅ Next Steps</h2>
        <ol>
            <li>If any sections show ❌, add the missing data via the admin panel links above</li>
            <li>Visit the actual about page: <a href="http://localhost/paxhitest/about" target="_blank"><strong>http://localhost/paxhitest/about</strong></a></li>
            <li>Clear browser cache (Ctrl+Shift+Delete) before testing</li>
            <li>If you see an orange banner on the right, it should now be hidden by the updated CSS</li>
        </ol>
    </div>
</body>
</html>
