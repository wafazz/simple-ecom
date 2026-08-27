@extends('layouts.admin')
@section('title', 'Banners')
@section('heading', 'Home page banners')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">
            Shown at the top of the shop front, in order. One banner appears on its own;
            two or more become a slider.
        </p>
        <a href="{{ route('admin.slides.create') }}" class="btn btn-shop">
            <i class="bi bi-plus-lg me-1"></i>Add banner
        </a>
    </div>

    <x-alerts />

    @if ($slides->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-images fs-1 text-muted"></i>
                <p class="mt-2 mb-1">No banners yet.</p>
                <p class="text-muted small mb-3">
                    Until you add one, the shop front shows its built-in heading.
                </p>
                <a href="{{ route('admin.slides.create') }}" class="btn btn-shop">Add the first banner</a>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th style="width:9rem">Banner</th>
                        <th>Wording</th>
                        <th>Buttons</th>
                        <th class="text-center">Order</th>
                        <th class="text-center">Shown</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($slides as $slide)
                        <tr>
                            <td>
                                @if ($slide->imageUrl())
                                    <img src="{{ $slide->imageUrl() }}" alt=""
                                         class="rounded" style="width:8rem;height:3.4rem;object-fit:cover;
                                                object-position:{{ $slide->objectPosition() }}">
                                @else
                                    <span class="badge text-bg-light">Text only</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $slide->headline }}</div>
                                @if ($slide->eyebrow)
                                    <div class="text-muted small">{{ $slide->eyebrow }}</div>
                                @endif
                            </td>
                            <td class="small text-muted">
                                @forelse ($slide->buttons() as $button)
                                    <div>{{ $button['label'] }}</div>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="text-center">{{ $slide->sort_order }}</td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('admin.slides.toggle', $slide) }}"
                                      data-confirm="{{ $slide->is_active ? 'Hide' : 'Show' }} this banner on the shop front?">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm {{ $slide->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                        {{ $slide->is_active ? 'Shown' : 'Hidden' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.slides.edit', $slide) }}"
                                   class="btn btn-sm btn-outline-primary">Edit</a>

                                {{-- A banner carries no order history, so this really
                                     does delete — the picture goes with it. --}}
                                <form method="POST" action="{{ route('admin.slides.destroy', $slide) }}"
                                      class="d-inline"
                                      data-confirm="Delete this banner? The picture is deleted too and cannot be recovered.">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="alert alert-secondary small mt-3">
        <strong>Artwork size.</strong> Export at <strong>2400 × 1000 pixels</strong> (JPEG or WebP,
        around 80% quality, under 2&nbsp;MB). Keep the important part of the picture on the
        side you choose under “Focus”, because the wording sits over the other side and phones
        crop the frame to its middle.
    </div>
@endsection
