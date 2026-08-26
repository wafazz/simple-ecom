<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — {{ config('shop.store_name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-body-tertiary">
<main class="container" style="max-width: 26rem;">
    <div class="card mt-5 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h5 mb-3">Admin Login</h1>

            <x-alerts />

            <form method="POST" action="{{ route('admin.login.attempt') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" required autofocus
                           value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password" required
                           class="form-control @error('password') is-invalid @enderror">
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="remember" id="remember" value="1" class="form-check-input">
                    <label for="remember" class="form-check-label">Remember me</label>
                </div>
                <button type="submit" class="btn btn-shop w-100">Log in</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
