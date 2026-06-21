<!DOCTYPE html>
<html lang="en">

<head>
	<title>{{ __('Two-Factor Authentication') }}</title>
	<!-- Meta -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="description" content="" />
	<meta name="keywords" content="">
	<meta name="author" content="" />
	<!-- Favicon icon -->
	<link rel="icon" href="{{ asset('logo.png') }}" type="image/x-icon">

	<!-- font css -->
	<link rel="stylesheet" href="{{ asset('dashboard/fonts/feather.css') }}">
	<link rel="stylesheet" href="{{ asset('dashboard/fonts/fontawesome.css') }}">
	<link rel="stylesheet" href="{{ asset('dashboard/fonts/material.css') }}">

	<!-- vendor css -->
	<link rel="stylesheet" href="{{ asset('dashboard/css/style.css') }}" id="main-style-link">
	<link rel="stylesheet" href="{{ asset('dashboard/css/custom.css') }}">

</head>

<body>
	<div class="auth-wrapper">
		<div class="auth-content text-center">
			<img src="{{ asset('logo.png') }}" alt="" class="img-fluid mb-4" style="max-width: 150px;">
			<div class="card borderless">
				<div class="row align-items-center text-center">
					<div class="col-md-12">
						<div class="card-body">
							<i class="fas fa-shield-alt text-primary mb-3" style="font-size: 48px;"></i>
							<h4 class="mb-3 f-w-400">{{ __('Two-Factor Authentication') }}</h4>
							<p class="text-muted mb-4">{{ __('Enter the 6-digit verification code sent to your email') }}</p>

							@if (session('error'))
							<div class="alert alert-danger alert-dismissible fade show" role="alert">
								<i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
								<button type="button" class="close" data-dismiss="alert" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							@endif

							@if (session('success'))
							<div class="alert alert-success alert-dismissible fade show" role="alert">
								<i class="fas fa-check-circle"></i> {{ session('success') }}
								<button type="button" class="close" data-dismiss="alert" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							@endif

							@if (session('info'))
							<div class="alert alert-info alert-dismissible fade show" role="alert">
								<i class="fas fa-info-circle"></i> {{ session('info') }}
								<button type="button" class="close" data-dismiss="alert" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							@endif

							<form method="POST" action="{{ route('admin.2fa.verify.submit') }}">
								@csrf
								<div class="form-group mb-3">
									<input type="text" 
									       name="code" 
									       class="form-control text-center @error('code') is-invalid @enderror" 
									       placeholder="000000"
									       maxlength="6"
									       style="font-size: 24px; letter-spacing: 10px; font-weight: bold;"
									       autocomplete="off"
									       autofocus
									       required>
									@error('code')
									<span class="invalid-feedback" role="alert">
										<strong>{{ $message }}</strong>
									</span>
									@enderror
								</div>

								<button type="submit" class="btn btn-primary btn-block mb-3">
									<i class="fas fa-check"></i> {{ __('Verify Code') }}
								</button>
							</form>

							<div class="text-center">
								<p class="text-muted mb-2">{{ __("Didn't receive the code?") }}</p>
								<form method="POST" action="{{ route('admin.2fa.resend') }}" class="d-inline">
									@csrf
									<button type="submit" class="btn btn-link">
										<i class="fas fa-redo"></i> {{ __('Resend Code') }}
									</button>
								</form>
							</div>

							<hr class="my-4">

							<a href="{{ route('login') }}" class="btn btn-secondary">
								<i class="fas fa-arrow-left"></i> {{ __('Back to Login') }}
							</a>

							<div class="alert alert-warning mt-4" role="alert">
								<div class="d-flex align-items-center justify-content-center">
									<i class="fas fa-clock mr-2"></i>
									<span>{{ __('Code expires in:') }}</span>
									<strong class="ml-2" id="countdown" style="font-size: 16px;">10:00</strong>
								</div>
								<div class="progress mt-2" style="height: 5px;">
									<div id="countdown-bar" class="progress-bar bg-warning" role="progressbar" style="width: 100%"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Required Js -->
	<script src="{{ asset('dashboard/js/vendor-all.min.js') }}"></script>
	<script src="{{ asset('dashboard/plugins/bootstrap/js/bootstrap.min.js') }}"></script>
	<script src="{{ asset('dashboard/js/pcoded.min.js') }}"></script>

	<script>
		// Auto-focus and format code input
		document.addEventListener('DOMContentLoaded', function() {
			const codeInput = document.querySelector('input[name="code"]');
			
			codeInput.addEventListener('input', function(e) {
				// Only allow numbers
				this.value = this.value.replace(/[^0-9]/g, '');
			});

			// Countdown timer (10 minutes default)
			let timeLeft = 10 * 60; // 10 minutes in seconds
			const totalTime = timeLeft;
			const countdownElement = document.getElementById('countdown');
			const countdownBar = document.getElementById('countdown-bar');

			function updateCountdown() {
				const minutes = Math.floor(timeLeft / 60);
				const seconds = timeLeft % 60;
				
				countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
				
				// Update progress bar
				const percentage = (timeLeft / totalTime) * 100;
				countdownBar.style.width = percentage + '%';
				
				// Change color based on time remaining
				if (percentage <= 25) {
					countdownBar.classList.remove('bg-warning');
					countdownBar.classList.add('bg-danger');
					countdownElement.classList.add('text-danger');
				} else if (percentage <= 50) {
					countdownBar.classList.remove('bg-success');
					countdownBar.classList.add('bg-warning');
				}
				
				if (timeLeft <= 0) {
					clearInterval(countdownInterval);
					countdownElement.textContent = 'EXPIRED';
					countdownElement.classList.add('text-danger');
					
					// Show expired message
					const form = document.querySelector('form');
					const expiredMsg = document.createElement('div');
					expiredMsg.className = 'alert alert-danger mt-3';
					expiredMsg.innerHTML = '<i class="fas fa-exclamation-triangle"></i> This verification code has expired. Please request a new code.';
					form.parentNode.insertBefore(expiredMsg, form.nextSibling);
					
					// Disable submit button
					document.querySelector('button[type="submit"]').disabled = true;
				} else {
					timeLeft--;
				}
			}

			// Update immediately
			updateCountdown();
			
			// Update every second
			const countdownInterval = setInterval(updateCountdown, 1000);
		});
	</script>
</body>

</html>
