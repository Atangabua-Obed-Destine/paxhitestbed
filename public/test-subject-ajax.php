<!DOCTYPE html>
<html>
<head>
    <title>Subject Marking Debug</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #333; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
        .warning { background: #fff3e0; padding: 15px; border-left: 4px solid #ff9800; margin: 20px 0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
        .test-button { background: #4CAF50; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 0; }
        .test-button:hover { background: #45a049; }
        #result { margin-top: 20px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>🔍 Subject Marking Debug Tool</h1>
        
        <div class="info">
            <strong>Test Parameters:</strong><br>
            • Faculty: FACULTY OF BUSINESS AND FINANCE (ID: 6)<br>
            • Program: HND ACCOUNTANCY (ID: 32)<br>
            • Session: OCTOBER-2025 (ID: 1)<br>
        </div>

        <h2>Step 1: Test AJAX Course Loading</h2>
        <p>This will simulate what happens when you select the Session dropdown:</p>
        
        <button class="test-button" onclick="testAjax()">Test AJAX Course Loading</button>
        
        <div id="result"></div>

        <h2>Step 2: Expected Courses</h2>
        <p>These are the courses that SHOULD appear in the dropdown (courses with class routines):</p>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Has Class Routine</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>ACC11O1H</td>
                    <td>Principles of Accounting</td>
                    <td class="success">✅ YES</td>
                </tr>
                <tr>
                    <td>ACC11O2H</td>
                    <td>OHADA Financial Accounting I</td>
                    <td class="success">✅ YES</td>
                </tr>
                <tr>
                    <td>ECO1102H</td>
                    <td>Statistics and Business Mathematics</td>
                    <td class="success">✅ YES</td>
                </tr>
                <tr>
                    <td>PHI1101</td>
                    <td>The Human Person</td>
                    <td class="success">✅ YES</td>
                </tr>
            </tbody>
        </table>

        <div class="warning">
            <strong>⚠️ Important:</strong> Only courses with class routines will appear. 
            If you need to see more courses, you need to create class routines for them first.
        </div>

        <h2>Step 3: Troubleshooting</h2>
        <p>If the AJAX test above returns 0 courses or shows an error:</p>
        <ol>
            <li><strong>Check if you're logged in as staff ID 0003</strong> (not Super Admin)</li>
            <li><strong>Verify staff has subject-marking permission</strong></li>
            <li><strong>Clear browser cache</strong> (Ctrl + Shift + R)</li>
            <li><strong>Check browser console</strong> for JavaScript errors (F12)</li>
        </ol>
    </div>

    <script>
        function testAjax() {
            $('#result').html('<p>⏳ Testing AJAX request...</p>');
            
            $.ajax({
                type: 'POST',
                url: '<?php echo url('/filter-techer-subject'); ?>',
                data: {
                    _token: '<?php echo csrf_token(); ?>',
                    session: 1,
                    program: 32
                },
                success: function(response) {
                    console.log('AJAX Response:', response);
                    
                    let html = '<div class="info">';
                    html += '<h3 class="success">✅ AJAX REQUEST SUCCESSFUL!</h3>';
                    html += '<p><strong>Courses Found:</strong> ' + response.length + '</p>';
                    
                    if (response.length > 0) {
                        html += '<table><thead><tr><th>Code</th><th>Title</th><th>ID</th></tr></thead><tbody>';
                        response.forEach(function(course) {
                            html += '<tr><td>' + course.code + '</td><td>' + course.title + '</td><td>' + course.id + '</td></tr>';
                        });
                        html += '</tbody></table>';
                        html += '<p class="success">✅ These courses should appear in your dropdown!</p>';
                    } else {
                        html += '<p class="error">❌ No courses returned. This means:</p>';
                        html += '<ul>';
                        html += '<li>Staff assignments are working, but</li>';
                        html += '<li>There are no courses with class routines for this program/session</li>';
                        html += '</ul>';
                    }
                    
                    html += '<h4>Raw JSON Response:</h4>';
                    html += '<pre>' + JSON.stringify(response, null, 2) + '</pre>';
                    html += '</div>';
                    
                    $('#result').html(html);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    
                    let html = '<div class="warning">';
                    html += '<h3 class="error">❌ AJAX REQUEST FAILED!</h3>';
                    html += '<p><strong>Status:</strong> ' + status + '</p>';
                    html += '<p><strong>Error:</strong> ' + error + '</p>';
                    html += '<h4>Response:</h4>';
                    html += '<pre>' + xhr.responseText + '</pre>';
                    html += '</div>';
                    
                    $('#result').html(html);
                }
            });
        }
    </script>
</body>
</html>
