<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — {{ config('shop.store_name') }}</title>
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('vendor/adminlte/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/app.css') }}">
</head>
<body class="login-page bg-body-secondary">
<div class="login-box">
    <div class="login-logo">
        <a href="{{ route('home') }}"><b>{{ config('shop.store_name') }}</b></a>
    </div>

    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Sign in to manage the store</p>

            <x-alerts />

            <form method="POST" action="{{ route('admin.login.attempt') }}">
                @csrf

                <div class="input-group mb-3">
                    <input type="email" name="email" id="email" required autofocus
                           value="{{ old('email') }}" placeholder="Email" aria-label="Email"
                           class="form-control @error('email') is-invalid @enderror">
                    <div class="input-group-text"><span class="bi bi-envelope"></span></div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password" id="password" required
                           placeholder="Password" aria-label="Password"
                           class="form-control @error('password') is-invalid @enderror">
                    {{-- type="button": a bare <button> inside a form submits it. --}}
                    <button type="button" id="togglePassword" class="input-group-text"
                            aria-controls="password" aria-pressed="false" aria-label="Show password"
                            title="Show password">
                        <span class="bi bi-eye-fill" id="togglePasswordIcon" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="row">
                    <div class="col-7">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                    </div>
                    <div class="col-5">
                        <button type="submit" class="btn btn-primary w-100">Sign in</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="{{ \App\Support\Asset::url('js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ \App\Support\Asset::url('vendor/adminlte/adminlte.min.js') }}"></script>
<script>
    // Reveal toggle. The field is only ever switched client-side — the form
    // posts the same value either way.
    document.getElementById('togglePassword').addEventListener('click', function () {
        const input = document.getElementById('password');
        const icon = document.getElementById('togglePasswordIcon');
        const hiding = input.type === 'text';
        const label = hiding ? 'Show password' : 'Hide password';

        // Changing `type` drops the caret to the end, so put it back.
        const start = input.selectionStart;
        const end = input.selectionEnd;

        input.type = hiding ? 'password' : 'text';
        icon.className = hiding ? 'bi bi-eye-fill' : 'bi bi-eye-slash-fill';

        this.setAttribute('aria-pressed', hiding ? 'false' : 'true');
        this.setAttribute('aria-label', label);
        this.setAttribute('title', label);

        input.focus();
        input.setSelectionRange(start, end);
    });
</script>
</body>
</html>
