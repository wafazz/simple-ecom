@extends('layouts.admin')
@section('title', 'Change Password')
@section('heading', $forced ? 'Set Your Password' : 'Change Password')

@section('content')
    @if ($forced)
        <div class="alert alert-warning" style="max-width: 34rem">
            This account is still using the password it was set up with. Choose your own
            before continuing — the rest of the admin panel is locked until you do.
        </div>
    @endif

    <div class="card" style="max-width: 34rem">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.password.update') }}">
                @csrf @method('PUT')

                <div class="mb-3">
                    <label for="current_password" class="form-label">Current password</label>
                    <input type="password" name="current_password" id="current_password" required
                           autocomplete="current-password"
                           class="form-control @error('current_password') is-invalid @enderror">
                    @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">New password</label>
                    <input type="password" name="password" id="password" required
                           autocomplete="new-password"
                           class="form-control @error('password') is-invalid @enderror">
                    <div class="form-text">At least 12 characters, with letters and numbers.</div>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Confirm new password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                           autocomplete="new-password" class="form-control">
                </div>

                <button type="submit" class="btn btn-shop">Save password</button>
            </form>
        </div>
    </div>
@endsection
