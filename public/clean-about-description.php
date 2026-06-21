<?php
/**
 * Clean About Us Description
 * Visit: http://localhost/paxhitest/clean-about-description.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Clean About Description</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        textarea { width: 100%; min-height: 300px; padding: 10px; font-family: monospace; }
        .btn { background: #0066CC; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #003366; }
    </style>
</head>
<body>
    <h1>🧹 Clean About Us Description</h1>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newDescription = $_POST['description'] ?? '';
        
        $updated = DB::table('about_us')
            ->where('id', 1)
            ->update(['description' => $newDescription]);
        
        if ($updated) {
            echo '<div class="card"><p class="success">✅ Description updated successfully!</p>';
            echo '<p>Visit the about page to see changes: <a href="http://localhost/paxhitest/about" target="_blank">View About Page</a></p></div>';
        } else {
            echo '<div class="card"><p class="error">❌ Failed to update description</p></div>';
        }
    }
    
    $about = DB::table('about_us')->first();
    ?>
    
    <div class="card">
        <h2>Current Description Content</h2>
        <form method="POST">
            <p><strong>Edit the description below and remove the unwanted orange banner content:</strong></p>
            <textarea name="description"><?php echo htmlspecialchars($about->description); ?></textarea>
            <br><br>
            <button type="submit" class="btn">💾 Update Description</button>
        </form>
    </div>
    
    <div class="card">
        <h3>💡 Suggestion: Clean Description</h3>
        <p>Here's a suggested clean version without the bullet points:</p>
        <textarea readonly><p><strong>PAXHI</strong> was founded with a clear purpose: <strong>to offer transformative, faith-driven education that responds directly to the challenges of our time</strong> especially in Regions like ours, affected by conflict and underdevelopment. We are more than a center of learning; we are a growing community of thinkers, innovators, and changemakers dedicated to <strong>peace, sustainability, and human development</strong>.</p>

<p>Our focus on <strong>agriculture</strong>, particularly <strong>rice production</strong>, positions us at the heart of food security and rural advancement. Beyond agriculture, our programs span <strong>technology, entrepreneurship, business, education, and leadership</strong>, equipping students with practical skills and moral clarity to drive meaningful change in their communities.</p>

<p>We are proud to be building a new kind of graduate: one who is competent, compassionate, and courageous—ready to rebuild, restore, and lead with purpose.</p></textarea>
    </div>
</body>
</html>
