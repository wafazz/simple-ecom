<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SlideRequest;
use App\Models\Slide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use League\Flysystem\FilesystemException;

/** Home page banners. */
class SlideController extends Controller
{
    public function index(): View
    {
        return view('admin.slides.index', ['slides' => Slide::query()->inOrder()->get()]);
    }

    public function create(): View
    {
        return view('admin.slides.form', [
            'slide' => new Slide([
                'is_active' => true,
                'focal' => 'center',
                // Put a new banner at the end rather than silently tying with
                // an existing one.
                'sort_order' => (int) Slide::max('sort_order') + 1,
            ]),
        ]);
    }

    public function store(SlideRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $path = $this->storeImage($request);

        Slide::create($this->attributes($data) + ['image_path' => $path]);

        return redirect()->route('admin.slides.index')->with('status', 'Banner added.');
    }

    public function edit(Slide $slide): View
    {
        return view('admin.slides.form', ['slide' => $slide]);
    }

    public function update(SlideRequest $request, Slide $slide): RedirectResponse
    {
        $data = $request->validated();
        $previous = $slide->image_path;
        $path = $this->storeImage($request);

        $attributes = $this->attributes($data);

        if ($path !== null) {
            $attributes['image_path'] = $path;
        } elseif ($request->boolean('remove_image')) {
            $attributes['image_path'] = null;
        }

        $slide->update($attributes);

        // Only after the row is saved: a failed delete should cost an orphaned
        // file, never a banner pointing at a picture that is gone.
        if (filled($previous) && ($slide->image_path !== $previous)) {
            Storage::disk('uploads')->delete($previous);
        }

        return redirect()->route('admin.slides.index')->with('status', 'Banner updated.');
    }

    public function toggle(Slide $slide): RedirectResponse
    {
        $slide->update(['is_active' => ! $slide->is_active]);

        return back()->with('status', $slide->is_active ? 'Banner shown.' : 'Banner hidden.');
    }

    /**
     * Banners carry no history, so unlike a category this really is deleted.
     * The file goes with it — nothing else can reference it.
     */
    public function destroy(Slide $slide): RedirectResponse
    {
        $path = $slide->image_path;
        $slide->delete();

        if (filled($path)) {
            Storage::disk('uploads')->delete($path);
        }

        return redirect()->route('admin.slides.index')->with('status', 'Banner deleted.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return collect($data)->only([
            'focal', 'eyebrow', 'headline', 'subtext',
            'cta_label', 'cta_url', 'cta2_label', 'cta2_url',
            'sort_order', 'is_active',
        ])->all();
    }

    /**
     * Stored under a framework-generated name, never the client's filename.
     *
     * The `uploads` disk is 'throw' => false, so a write failure is silent and
     * putFile() simply returns false — which would be written into image_path
     * as an empty value, giving a banner that looks saved and shows nothing.
     * Both outcomes surface on the field the admin touched instead
     * (ProductController::storeImage documents the 500 this once caused).
     */
    private function storeImage(SlideRequest $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        try {
            $path = Storage::disk('uploads')->putFile('slides', $request->file('image'));
        } catch (FilesystemException $e) {
            $this->failUpload($e->getMessage());
        }

        if (! is_string($path) || $path === '') {
            $this->failUpload('putFile() returned no path.');
        }

        return $path;
    }

    /** Detail to the log; absolute server paths do not belong on screen (§17). */
    private function failUpload(string $reason): never
    {
        Log::error('Banner upload failed.', [
            'disk_root' => config('filesystems.disks.uploads.root'),
            'reason' => $reason,
        ]);

        throw ValidationException::withMessages([
            'image' => 'The banner could not be saved. The uploads folder is most '
                .'likely not writable by the web server — see DEPLOYMENT.md. '
                .'Nothing was changed.',
        ]);
    }
}
