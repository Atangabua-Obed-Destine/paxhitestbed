<!-- resources/views/maintenance.blade.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAXHI Institute | Maintenance Mode</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        .container {
            background: #fff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
        }
        h1 {
            font-size: 2.2rem;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        p {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 20px;
        }
        .logo {
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 25px;
            font-size: 0.9rem;
            color: #777;
        }
        .loader {
            border: 6px solid #f3f3f3;
            border-top: 6px solid #007bff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            margin: 20px auto;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="https://paxhi.org/uploads/setting/PAXHI_LOGO_1753146564_1753331669.png" alt="PAXHI Logo" width="100">
        </div>
        <h1>We’ll be back soon!</h1>
        <p>
            The <strong>PAXHI Institute </strong> is currently undergoing 
            scheduled maintenance. We’re working hard to bring you a better experience.
        </p>
        <div class="loader"></div>
        <p>Please check back later.</p>
        <div class="footer">
            &copy; {{ date('Y') }} PAXHI Institute. All Rights Reserved.
        </div>
    </div>
</body>
</html>
