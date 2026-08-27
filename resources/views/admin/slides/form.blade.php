@extends('layouts.admin')
@section('title', $slide->exists ? 'Edit banner' : 'Add banner')
@section('heading', $slide->exists ? 'Edit banner' : 'Add banner')

@section('content')
    <x-alerts />

    <form method="POST" enctype="multipart/form-data"
          action="{{ $slide->exists ? route('admin.slides.update', $slide) : route('admin.slides.store') }}">
        @csrf
        @if ($slide->exists) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">Wording</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="eyebrow" class="form-label">Small line above <span class="text-muted">(optional)</span></label>
                            <input type="text" name="eyebrow" id="eyebrow" maxlength="80"
                                   value="{{ old('eyebrow', $slide->eyebrow) }}"
                                   placeholder="PFC OFFICIAL MERCH 2026/27"
                                   class="form-control @error('eyebrow') is-invalid @enderror">
                            @error('eyebrow')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="headline" class="form-label">Headline</label>
                            <input type="text" name="headline" id="headline" required maxlength="120"
                                   value="{{ old('headline', $slide->headline) }}"
                                   placeholder="Everyday pieces, made to be worn out."
                                   class="form-control @error('headline') is-invalid @enderror">
                            @error('headline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-0">
                            <label for="subtext" class="form-label">Supporting line <span class="text-muted">(optional)</span></label>
                            <textarea name="subtext" id="subtext" rows="2" maxlength="300"
                                      class="form-control @error('subtext') is-invalid @enderror">{{ old('subtext', $slide->subtext) }}</textarea>
                            @error('subtext')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Buttons</div>
                    <div class="card-body">
                        <p class="text-muted small">
                            A button appears only when it has both a label and a link. Use a path
                            like <code>/products</code> for a page on this shop.
                        </p>

                        @foreach ([['cta_label', 'cta_url', 'First button', 'Shop everything', '/products'],
                                   ['cta2_label', 'cta2_url', 'Second button', 'Track an order', '/order-status']] as [$labelField, $urlField, $title, $labelHint, $urlHint])
                            <div class="row g-2 mb-3">
                                <div class="col-sm-5">
                                    <label for="{{ $labelField }}" class="form-label">{{ $title }} label</label>
                                    <input type="text" name="{{ $labelField }}" id="{{ $labelField }}" maxlength="40"
                                           value="{{ old($labelField, $slide->{$labelField}) }}"
                                           placeholder="{{ $labelHint }}"
                                           class="form-control @error($labelField) is-invalid @enderror">
                                    @error($labelField)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-sm-7">
                                    <label for="{{ $urlField }}" class="form-label">Link</label>
                                    <input type="text" name="{{ $urlField }}" id="{{ $urlField }}" maxlength="255"
                                           value="{{ old($urlField, $slide->{$urlField}) }}"
                                           placeholder="{{ $urlHint }}"
                                           class="form-control @error($urlField) is-invalid @enderror">
                                    @error($urlField)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">Picture</div>
                    <div class="card-body">
                        @if ($slide->imageUrl())
                            <img src="{{ $slide->imageUrl() }}" alt="" class="img-fluid rounded mb-2">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="remove_image"
                                       value="1" id="remove_image">
                                <label class="form-check-label" for="remove_image">
                                    Remove this picture (the banner becomes text only)
                                </label>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="image" class="form-label">
                                {{ $slide->imageUrl() ? 'Replace picture' : 'Upload picture' }}
                                <span class="text-muted">(optional)</span>
                            </label>
                            <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp"
                                   class="form-control @error('image') is-invalid @enderror">
                            @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                <strong>2400 × 1000 pixels.</strong> JPEG or WebP, under 2&nbsp;MB.
                                Leave it empty for a plain worded banner.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="focal" class="form-label">Focus</label>
                            <select name="focal" id="focal" class="form-select @error('focal') is-invalid @enderror">
                                @foreach (\App\Models\Slide::FOCAL as $value => $label)
                                    <option value="{{ $value }}" @selected(old('focal', $slide->focal) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('focal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Which part of the picture to keep when a narrow screen crops it.
                                The wording sits on the left, so a subject on the right usually
                                wants <strong>Right</strong>.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Placement</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="sort_order" class="form-label">Order</label>
                            <input type="number" name="sort_order" id="sort_order" min="0" max="999" required
                                   value="{{ old('sort_order', $slide->sort_order ?? 0) }}"
                                   class="form-control @error('sort_order') is-invalid @enderror">
                            @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Lowest first.</div>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                   id="is_active" @checked(old('is_active', $slide->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Show on the shop front</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-shop">
                {{ $slide->exists ? 'Save banner' : 'Add banner' }}
            </button>
            <a href="{{ route('admin.slides.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
@endsection
