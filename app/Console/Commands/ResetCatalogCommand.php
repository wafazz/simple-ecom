<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Empties the catalogue and/or every order, and sends the auto-increment IDs
 * back to 1. TRUNCATE rather than DELETE, because only TRUNCATE resets the
 * counter.
 *
 * The store's identity is deliberately left alone: users, settings,
 * secure_settings and integration_tokens survive, so the admin can still log in
 * and the ToyyibPay/EasyParcel credentials do not have to be entered again.
 *
 * orders.order_no needs no special handling. It is derived per day by counting
 * that day's rows (CheckoutController::generateOrderNumber), not from a stored
 * counter, so emptying `orders` restarts it at ORD-<today>-0001 by itself.
 */
class ResetCatalogCommand extends Command
{
    protected $signature = 'shop:reset-catalog
                            {--force : Skip the confirmation prompt}
                            {--orders-only : Clear orders but keep the catalogue}
                            {--restore-stock : Return stock from the cleared orders to the variants (needs --orders-only)}
                            {--images : Also delete the uploaded product image files}';

    protected $description = 'Delete all orders, products and categories, resetting IDs to 1';

    /**
     * Children first, then parents. Foreign key checks are off while this runs,
     * so the order is not strictly required — but it keeps the list correct for
     * anyone who copies it into a plain SQL session.
     */
    private const ORDER_TABLES = [
        'shipments',
        'payments',
        'order_items',
        'orders',
    ];

    private const CATALOGUE_TABLES = [
        'product_images',
        'product_variants',
        'products',
        'categories',
    ];

    public function handle(): int
    {
        $ordersOnly = (bool) $this->option('orders-only');

        if ($error = $this->rejectContradictoryOptions($ordersOnly)) {
            $this->error($error);

            return self::FAILURE;
        }

        $tables = $ordersOnly
            ? self::ORDER_TABLES
            : [...self::ORDER_TABLES, ...self::CATALOGUE_TABLES];

        $counts = [];

        foreach ($tables as $table) {
            $counts[] = [$table, DB::table($table)->count()];
        }

        $this->table(['Table', 'Rows to delete'], $counts);

        if (! $this->option('force')) {
            $this->warn('This cannot be undone. Take a database backup first.');

            // Defaults to no, so a non-interactive run without --force aborts.
            if (! $this->confirm('Delete all of the above and reset IDs to 1?')) {
                $this->line('Aborted. Nothing was deleted.');

                return self::SUCCESS;
            }
        }

        // Read the quantities BEFORE the truncate, while order_items still
        // exists. Nothing in the app ever puts stock back — not even cancelling
        // or refunding an order — so what is counted here cannot double-count.
        //
        // ⚠ Only orders that actually SETTLED. Stock is taken at settlement
        // (PaymentController), not at checkout, so a pending or failed order
        // never touched it and crediting one back would invent stock that was
        // never sold. Refunded counts: the money came back, the goods did not.
        $stock = $this->option('restore-stock')
            ? DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.payment_status', [
                    PaymentStatus::Paid->value,
                    PaymentStatus::Refunded->value,
                ])
                ->select('product_variant_id', DB::raw('SUM(qty) AS qty'))
                ->groupBy('product_variant_id')
                ->pluck('qty', 'product_variant_id')
            : collect();

        // MariaDB refuses to truncate a table that another table points at, so
        // the constraints come off for the duration and go back on in finally
        // even if a truncate throws.
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
                $this->line("  truncated {$table}");
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->restoreStock($stock);
        $this->handleImages($ordersOnly);

        $this->info($ordersOnly
            ? 'Orders cleared. Next order is ID 1 and ORD-'.now()->format('Ymd').'-0001.'
            : 'Catalogue and orders cleared. Next IDs start at 1.');

        return self::SUCCESS;
    }

    /**
     * Both flags are meaningless — and --images is actively destructive —
     * against a full reset or a catalogue that is staying put.
     */
    private function rejectContradictoryOptions(bool $ordersOnly): ?string
    {
        if ($this->option('restore-stock') && ! $ordersOnly) {
            return 'The --restore-stock option needs --orders-only: a full reset deletes the variants the stock would go back to.';
        }

        if ($this->option('images') && $ordersOnly) {
            return 'The --images option cannot be combined with --orders-only: those files still belong to products you are keeping.';
        }

        return null;
    }

    private function restoreStock(Collection $stock): void
    {
        if ($stock->isEmpty()) {
            return;
        }

        foreach ($stock as $variantId => $qty) {
            DB::table('product_variants')->where('id', $variantId)->increment('stock_qty', $qty);
        }

        $this->line("  returned stock to {$stock->count()} variant(s)");
    }

    private function handleImages(bool $ordersOnly): void
    {
        if ($ordersOnly) {
            return;
        }

        if ($this->option('images')) {
            // Only the products/ subfolder: public/uploads holds a tracked
            // .gitignore that must survive.
            Storage::disk('uploads')->deleteDirectory('products');
            // Put the (empty) folder back. The uploads disk is configured
            // 'throw' => false, so if a later upload had to create it and could
            // not, the failure would be silent.
            Storage::disk('uploads')->makeDirectory('products');
            $this->line('  deleted uploaded product images');

            return;
        }

        if (Storage::disk('uploads')->exists('products')) {
            $this->comment('Image files under public/uploads/products are now orphaned. Re-run with --images to remove them.');
        }
    }
}
