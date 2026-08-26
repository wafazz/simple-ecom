{{-- Rendered by error pages too. An unmatched URL never passes through the
     `web` group, so ShareErrorsFromSession has not run and $errors does not
     exist — without these guards a 404 becomes a 500. --}}
@if (session()->isStarted() && session('status'))
    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
@endif

@if (session()->isStarted() && session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

@if (isset($errors) && $errors->any())
    <div class="alert alert-danger" role="alert">
        <p class="mb-1 fw-semibold">Please fix the following:</p>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
