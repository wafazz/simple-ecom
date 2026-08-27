<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

/** Shop policies the admin writes themselves. */
class PolicyController extends Controller
{
    /**
     * The return & exchange policy.
     *
     * 404 while it is empty, rather than serving an empty page or a stand-in
     * the shop never agreed to. A returns policy is something a customer may
     * hold the shop to, so nothing appears here that the owner did not type.
     */
    public function returns(): View
    {
        $body = trim((string) Setting::get('return_policy'));

        abort_if($body === '', 404);

        return view('storefront.policy', [
            'heading' => 'Return & Exchange Policy',
            'body' => $body,
            // The row's own timestamp — no separate field to keep in step.
            'updatedAt' => Setting::query()->where('key', 'return_policy')->value('updated_at'),
            // Passed rather than added to the global view composer: one page
            // needs it, and every other request would pay for the share.
            'storeEmail' => Setting::get('store_email'),
        ]);
    }
}
