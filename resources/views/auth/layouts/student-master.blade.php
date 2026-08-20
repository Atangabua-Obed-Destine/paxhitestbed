<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title') - {{ institution_name() }}</title>
    
    <!-- Favicon -->
    @if(isset($setting) && is_file('uploads/setting/'.$setting->favicon_path))
    <link rel="icon" href="{{ asset('uploads/setting/'.$setting->favicon_path) }}" type="image/x-icon">
    @endif
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            overflow-x: hidden;
        }
        
        /* ===== Student Login Wrapper ===== */
        .student-login-wrapper {
            min-height: 100vh;
            background: #ffffff;
        }
        
        /* ===== Left Info Panel ===== */
        .login-info-panel {
            background: linear-gradient(135deg, #003366 0%, #0066CC 100%);
            padding: 60px 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-info-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .login-info-panel::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .info-content {
            position: relative;
            z-index: 2;
            max-width: 550px;
        }
        
        .university-logo {
            text-align: center;
            animation: fadeInDown 1s ease-out;
        }
        
        .university-logo img {
            max-width: 180px;
            height: auto;
        }
        
        .welcome-title {
            font-size: 42px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 15px;
            line-height: 1.2;
            animation: fadeInUp 1s ease-out 0.2s both;
        }
        
        .welcome-subtitle {
            font-size: 20px;
            color: #ffffff;
            opacity: 0.9;
            margin-bottom: 50px;
            font-weight: 300;
            animation: fadeInUp 1s ease-out 0.3s both;
        }
        
        /* Feature Items */
        .info-features {
            margin-bottom: 50px;
        }
        
        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .feature-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(10px);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #FF6B35, #FF8C35);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .feature-icon i {
            font-size: 28px;
            color: #ffffff;
        }
        
        .feature-text h5 {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
        }
        
        .feature-text p {
            font-size: 14px;
            color: #ffffff;
            opacity: 0.9;
            margin: 0;
            line-height: 1.6;
        }
        
        /* Stats Row */
        .stats-row {
            display: flex;
            justify-content: space-around;
            padding: 30px 0;
            border-top: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: #FF6B35;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #ffffff;
            opacity: 0.9;
            font-weight: 500;
        }
        
        /* ===== Right Form Panel ===== */
        .login-form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 30px;
            background: #f8f9fa;
        }
        
        .form-container {
            width: 100%;
            max-width: 480px;
            animation: fadeInRight 1s ease-out;
        }
        
        .mobile-logo {
            text-align: center;
        }
        
        .mobile-logo img {
            max-width: 120px;
            height: auto;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0066CC, #003366);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            animation: scaleIn 0.5s ease-out 0.5s both;
        }
        
        .icon-wrapper i {
            font-size: 40px;
            color: #ffffff;
        }
        
        .login-title {
            font-size: 32px;
            font-weight: 700;
            color: #003366;
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            font-size: 15px;
            color: #666666;
            margin: 0;
        }
        
        /* Login Form */
        .login-form {
            background: #ffffff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }
        
        /* Alert Custom */
        .alert-custom {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            font-size: 14px;
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .alert-custom i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        
        .form-group-custom {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #003366;
            margin-bottom: 10px;
        }
        
        .form-label i {
            color: #0066CC;
            margin-right: 8px;
        }
        
        .form-control-custom {
            width: 100%;
            height: 50px;
            padding: 0 20px;
            font-size: 15px;
            color: #333333;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control-custom:focus {
            outline: none;
            border-color: #0066CC;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(0, 102, 204, 0.1);
        }
        
        .form-control-custom.is-invalid {
            border-color: #dc3545;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666666;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: #0066CC;
        }
        
        .invalid-feedback-custom {
            display: block;
            margin-top: 8px;
            font-size: 13px;
            color: #dc3545;
        }
        
        .invalid-feedback-custom i {
            margin-right: 5px;
        }
        
        /* Form Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            cursor: pointer;
            accent-color: #0066CC;
        }
        
        .remember-me label {
            font-size: 14px;
            color: #666666;
            cursor: pointer;
            margin: 0;
        }
        
        .forgot-password a {
            font-size: 14px;
            color: #0066CC;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .forgot-password a:hover {
            color: #003366;
        }
        
        /* Login Button */
        .btn-login {
            width: 100%;
            height: 55px;
            background: linear-gradient(135deg, #0066CC, #003366);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(0, 102, 204, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0, 102, 204, 0.4);
        }
        
        .btn-login i {
            margin-left: 10px;
            transition: transform 0.3s ease;
        }
        
        .btn-login:hover i {
            transform: translateX(5px);
        }
        
        /* Register Link */
        .register-link {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-link p {
            font-size: 14px;
            color: #666666;
            margin: 0;
        }
        
        .register-link a {
            color: #0066CC;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
        }
        
        .register-link a:hover {
            color: #003366;
        }
        
        /* Login Footer */
        .login-footer {
            text-align: center;
        }
        
        .help-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
        }
        
        .help-links a {
            font-size: 14px;
            color: #666666;
            text-decoration: none;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .help-links a i {
            margin-right: 6px;
            color: #0066CC;
        }
        
        .help-links a:hover {
            color: #0066CC;
        }
        
        .copyright-text {
            font-size: 13px;
            color: #999999;
            margin: 0;
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .welcome-title {
                font-size: 32px;
            }
            
            .welcome-subtitle {
                font-size: 18px;
            }
            
            .login-form-panel {
                padding: 40px 20px;
            }
            
            .login-form {
                padding: 30px 25px;
            }
        }
        
        @media (max-width: 767px) {
            .login-title {
                font-size: 26px;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .help-links {
                flex-direction: column;
                gap: 15px;
            }
        }
        
        @media (max-width: 575px) {
            .icon-wrapper {
                width: 70px;
                height: 70px;
            }
            
            .icon-wrapper i {
                font-size: 32px;
            }
            
            .login-form {
                padding: 25px 20px;
            }
            
            .form-control-custom {
                height: 45px;
            }
            
            .btn-login {
                height: 50px;
            }
        }
    </style>
</head>
<body>

    @yield('content')

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 50
        });
    </script>
</body>
</html>
