<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReturnPolicyRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * The return & exchange policy.
 *
 * Its own screen rather than a card on Settings: this is the shop's writing,
 * not its configuration, and it is edited on its own occasions. The text still
 * lives in the settings table — one key is not worth a table of its own.
 */
class PolicyController extends Controller
{
    public function edit(): View
    {
        $row = Setting::query()->where('key', 'return_policy')->first();
        $body = trim((string) ($row->value ?? ''));

        return view('admin.policy', [
            'body' => $body,
            'published' => $body !== '',
            'updatedAt' => $row?->updated_at,
        ]);
    }

    public function update(ReturnPolicyRequest $request): RedirectResponse
    {
        $body = $request->body();

        Setting::put('return_policy', $body);

        Log::info('Return policy updated', [
            'published' => $body !== '',
            'characters' => mb_strlen($body),
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.policy.edit')
            ->with('status', $body === ''
                ? 'Policy cleared. The page is no longer published.'
                : 'Policy saved and published.');
    }
}
