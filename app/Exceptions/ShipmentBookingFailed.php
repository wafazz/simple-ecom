<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The booking definitively did not happen and nothing was charged.
 *
 * Missing data, a rejected payload, a 4xx, or a per-shipment `"status":
 * "error"` in an otherwise-200 response. Safe to fix and retry.
 */
class ShipmentBookingFailed extends RuntimeException {}
