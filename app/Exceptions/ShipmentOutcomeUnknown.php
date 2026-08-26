<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The courier request may or may not have spent credit.
 *
 * Thrown for a timeout, a 5xx, or a body that could not be read. It is
 * deliberately a DIFFERENT type from an ordinary failure: an ordinary failure
 * is safe to retry, and this is not. Retrying a call that may already have
 * charged the wallet is how a store pays twice.
 *
 * Shipments in this state go to `needs_reconciliation` and wait for a human to
 * check the EasyParcel dashboard.
 */
class ShipmentOutcomeUnknown extends RuntimeException {}
