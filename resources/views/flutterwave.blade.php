<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flutterwave Payment Forms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
<div class="container">
    <div class="row mt-5 mb-5">
        <div class="col-10 offset-1 mt-5">
            <div class="card">
                <div class="card-header bg-primary">
                    <h3 class="text-white">Flutterwave Payment Forms</h3>
                </div>

                <div class="card-body">
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>{{ $message }}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif

                    @if ($message = Session::get('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>{{ $message }}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif

                    
                    <form method="POST" action="{{ route('payment.flutterwave.process') }}" role="form">
                        @csrf

                        <input type="hidden" name="fee_id" value="12">

                        <p>
                            <button class="btn btn-success btn-lg btn-block" type="submit" value="Proceed to Payment">
                                <i class="fa fa-plus-circle fa-lg"></i> Pay Now!
                            </button>
                        </p>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>