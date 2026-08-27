@extends('layouts.admin')
@section('title', 'Return policy')
@section('heading', 'Return & exchange policy')

@section('content')
    <x-alerts />

    <div class="row g-4">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('admin.policy.update') }}">
                @csrf @method('PUT')

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Policy text</span>
                        @if ($published)
                            <span class="badge text-bg-success">Published</span>
                        @else
                            <span class="badge text-bg-secondary">Not published</span>
                        @endif
                    </div>

                    <div class="card-body">
                        <label for="return_policy" class="form-label visually-hidden">Policy text</label>
                        <textarea name="return_policy" id="return_policy" rows="18" maxlength="20000"
                                  placeholder="Items may be returned within 7 days of delivery, unworn and with tags attached.&#10;&#10;Printed namesets cannot be returned unless faulty."
                                  class="form-control font-monospace @error('return_policy') is-invalid @enderror"
                                  style="font-size: .92rem; line-height: 1.6;">{{ old('return_policy', $body) }}</textarea>
                        @error('return_policy') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        <div class="form-text">
                            Plain text. Leave a <strong>blank line</strong> between paragraphs;
                            a single line break stays a line break. Anything that looks like
                            HTML is shown as written, never run.
                        </div>
                    </div>

                    <div class="card-footer d-flex gap-2 align-items-center flex-wrap">
                        {{-- Saving an empty box unpublishes the page, which is a
                             bigger step than "save" suggests. --}}
                        <button class="btn btn-shop"
                                data-confirm="Save the return policy? Clearing the box unpublishes the page.">
                            Save policy
                        </button>

                        @if ($published)
                            <a href="{{ route('policy.returns') }}" target="_blank" rel="noopener"
                               class="btn btn-quiet">
                                <i class="bi bi-box-arrow-up-right me-1"></i>View page
                            </a>
                        @endif

                        @if ($updatedAt)
                            <span class="text-muted small ms-auto">
                                Last saved {{ $updatedAt->format('j M Y, H:i') }}
                            </span>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Where this appears</div>
                <div class="card-body small">
                    <p>
                        Published at
                        <code>/return-policy</code>, and linked in the shop footer under
                        <strong>Orders</strong>.
                    </p>
                    <p class="mb-0">
                        <strong>An empty policy is not published.</strong> The page returns
                        “not found” and the footer link disappears, so customers never meet a
                        blank policy or one the shop did not write.
                    </p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Worth covering</div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3 d-grid gap-1">
                        <li>How many days a customer has to ask</li>
                        <li>What condition goods must come back in</li>
                        <li>Who pays return postage</li>
                        <li>Whether printed namesets can be returned</li>
                        <li>How a refund or exchange is issued, and how long it takes</li>
                        <li>How to start one — the email or phone number to use</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
