# Invoice Capture Bug Fixes — Plan

Identified issues in the CoreWebhook capture flow where Magento invoices are not reliably
created or linked correctly after a Wallee payment capture webhook.

---

## Fix 1: Parent Transaction ID set to `null` in `captureInvoice`

**File:** `Model/CoreWebhook/TransactionInvoice/CaptureCommand.php`

**Problem:**
`setTransactionId(null)` is called before reading the existing ID, so
`setParentTransactionId` always receives `null`. The Magento capture transaction is never
linked to its parent auth transaction.

**Current code:**
```php
$payment->setTransactionId(null);
$payment->setParentTransactionId($payment->getTransactionId()); // already null!
$payment->setIsTransactionClosed(true);
```

**Fix:** Read the existing transaction ID first, then clear it.
```php
$payment->setParentTransactionId($payment->getTransactionId()); // capture before clearing
$payment->setTransactionId(null);
$payment->setIsTransactionClosed(true);
```

---

## Fix 2: Canceled invoice silently blocks re-capture

**File:** `Model/CoreWebhook/TransactionInvoice/CaptureCommand.php`

**Problem:**
`needsCapture` evaluates to `false` for `STATE_CANCELED` invoices because the condition only
allows `STATE_OPEN`. If a prior failure canceled the invoice and a capture webhook then
arrives, no new invoice is created — the canceled invoice is returned as `$finalInvoice`
and the order is moved to `PROCESSING` with no valid paid invoice.

**Current code:**
```php
$needsCapture = !($existingInvoice instanceof InvoiceInterface)
    || $existingInvoice->getState() == Invoice::STATE_OPEN;
```

**Fix:** Also treat a canceled invoice as requiring a fresh capture, and clear it so
`captureInvoice` creates a new one.
```php
$isCanceled = $existingInvoice instanceof InvoiceInterface
    && $existingInvoice->getState() === Invoice::STATE_CANCELED;

$needsCapture = !($existingInvoice instanceof InvoiceInterface)
    || $existingInvoice->getState() === Invoice::STATE_OPEN
    || $isCanceled;

// Don't pass the canceled invoice as a fallback — force a fresh one
if ($isCanceled) {
    $existingInvoice = null;
}
```

---

## Fix 3: Silent failure when `captureInvoice` returns `null` — no webhook retry

**File:** `Model/CoreWebhook/TransactionInvoice/CaptureCommand.php`

**Problem:**
When `captureInvoice` returns `null`, only a warning is logged and the method returns
early. Because no exception is thrown, the webhook system treats this as a success and
does not retry. The order is left without a paid invoice.

**Current code:**
```php
if (!$finalInvoice) {
    $this->logger->warning(
        "No invoice could be found or created for TransactionInvoice {$invoiceEntity->getId()}."
    );
    return $order;
}
```

**Fix:** Throw a `CommandException` to signal a retryable failure.
```php
if (!$finalInvoice) {
    throw new CommandException(sprintf(
        'CaptureCommand: could not find or create an invoice for TransactionInvoice %s on order %s — deferring for retry.',
        $invoiceEntity->getId(),
        $order->getIncrementId()
    ));
}
```

> **Note:** Verify that `CommandException` is retryable (not a terminal failure) in the
> PluginCore webhook infrastructure before applying this — check how it behaves relative
> to a permanent `WebhookException` if one exists.

---

## Fix 4: `SubmitQuote` skips invoice creation for PWA / headless flow

**File:** `Observer/SubmitQuote.php`

**Problem:**
`isExternalPaymentUrl()` returns `true` when a PWA or headless storefront has provided
custom `success_url` / `failure_url` values on the transaction info record (rather than
using Magento's standard checkout return routes). In this case `SubmitQuote` returns
early and skips invoice creation — the Wallee hosted payment page is still used as normal,
but the post-payment redirect goes to the external frontend instead of Magento's own pages.

The capture webhook is then solely responsible for creating the invoice from scratch.
If that also fails (see Fix 3), no invoice ever exists.

**Consideration:**
The skip may be intentional — in a PWA flow the quote submit lifecycle can behave
differently and creating a capture-pending invoice at that point may not be safe.
Confirm whether a capture-pending invoice should be created at checkout time for the
PWA flow, or whether deferring to the capture webhook (and hardening that path) is
the correct approach.

**Option A — intended behaviour, harden the capture path:**
No change to `SubmitQuote`. Ensure Fix 3 is in place so the capture webhook retries
until an invoice can be created.

**Option B — create the invoice at checkout for all flows:**
Remove the early return so `createInvoice` is always called, with `capture_pending = true`
on the resulting invoice. The capture webhook then finds it via `getInvoiceForTransaction`
and calls `$invoice->pay()` as normal.

---

## Fix 5: Transaction ID mismatch in `getInvoiceForTransaction`

**File:** `Model/CoreWebhook/OrderInvoiceTrait.php`

**Problem:**
The lookup uses `$sdkSpaceId . '_' . $sdkTransactionId` where `$sdkSpaceId` comes from
`$transaction->getLinkedSpaceId()`. If this differs from the space ID stored on the order
at checkout time (e.g. multi-space or data inconsistency), the `strpos` check fails and
the existing invoice is not found. The code then tries to create a new invoice from scratch,
which fails because `canInvoice()` returns false (items already invoiced).

**Current lookup:**
```php
if (\strpos((string) $invoice->getTransactionId(), $sdkSpaceId . '_' . $sdkTransactionId) === 0
    && $invoice->getState() != Invoice::STATE_CANCELED) {
    return $invoice;
}
```

**Fix:** Add a fallback that searches by transaction ID alone (without space ID prefix)
when the full-prefix match fails, or log a clear mismatch warning to make debugging easier.
```php
$fullPrefix = $sdkSpaceId . '_' . $sdkTransactionId;
$idOnly     = (string) $sdkTransactionId;

foreach ($order->getInvoiceCollection() as $invoice) {
    $txId = (string) $invoice->getTransactionId();
    if ($invoice->getState() === Invoice::STATE_CANCELED) {
        continue;
    }
    if (\strpos($txId, $fullPrefix) === 0) {
        return $invoice;
    }
    // Fallback: match by transaction ID alone and log the space ID mismatch
    if (\strpos($txId, $idOnly) !== false) {
        $this->logger->warning(sprintf(
            'getInvoiceForTransaction: space ID mismatch for invoice %s (expected %s, got prefix from "%s"). Using fallback match.',
            $invoice->getId(), $fullPrefix, $txId
        ));
        return $invoice;
    }
}
```

---

## Fix 0: Wrong attribute key clears the wrong `capture_pending` flag (ROOT CAUSE of "stays in pending" display)

**File:** `Model/CoreWebhook/TransactionInvoice/CaptureCommand.php:210`

**Problem:**
Magento's `DataObject::__call()` converts method names to snake_case data keys via
`_underscore()`. The two method names resolve to completely different keys:

| Method | Data key set |
|---|---|
| `setWalleeCapturePending` (CoreWebhook) | `white_label_machine_name_capture_pending` |
| `setWalleeCapturePending` (everywhere else) | `wallee_32_capture_pending` |

`SubmitQuote` sets `wallee_32_capture_pending = true` at checkout.
The admin invoice view (`Block/Adminhtml/Sales/Order/Invoice/View.php:44`) reads it back
with `getWalleeCapturePending()` (same key). The CoreWebhook clears
`white_label_machine_name_capture_pending` — a key that has nothing to do with the DB
column — so the flag stays `true` and the admin UI permanently shows "capture pending."

**Fix:** ✅ Applied — changed `setWalleeCapturePending` to
`setWalleeCapturePending` on line 210.

---

## Fix 6: Invoice changes not persisted — `orderRepository->save()` does not save related objects

**File:** `Model/CoreWebhook/TransactionInvoice/CaptureCommand.php`

**Problem:**
`$invoice->pay()` sets the invoice state to `STATE_PAID` in memory and
`$order->addRelatedObject($invoice)` stages it for saving. However,
`$this->orderRepository->save($order)` calls the Order resource model directly via
`$this->metadata->getMapper()->save($entity)`, bypassing `AbstractModel::save()`. This
means the related objects array is never iterated and the invoice changes are **never
written to the database**. The invoice stays in `STATE_OPEN` / `capture_pending = true`.

This affects both the CoreWebhook and legacy `CaptureCommand` — neither explicitly saves
the invoice.

`SubmitQuote` avoids this by using `DB\Transaction` to save both objects atomically:
```php
$this->dbTransactionFactory->create()
    ->addObject($order)
    ->addObject($invoice)
    ->save();
```

**Fix:** Inject `\Magento\Framework\DB\TransactionFactory` into `CaptureCommand` and
replace the standalone `$this->orderRepository->save($order)` with a DB transaction that
saves both the order and the invoice together.

```php
// Constructor — add:
private readonly \Magento\Framework\DB\TransactionFactory $dbTransactionFactory,

// In execute(), replace:
$this->orderRepository->save($order);

// With:
$dbTransaction = $this->dbTransactionFactory->create()
    ->addObject($order);

if ($finalInvoice) {
    $dbTransaction->addObject($finalInvoice);
}

$dbTransaction->save();
```

> **Note:** If `CommandException` is thrown (Fix 3), ensure the DB transaction has not
> already been partially committed. The throw should happen before the save call, which
> it already does at line 141-146.

---

## Suggested fix order

1. **Fix 6** (invoice not persisted) — **root cause of the "stays pending" symptom**; most
   impactful, required for any capture to work correctly.
2. **Fix 1** (parent transaction ID) — small change, fixes transaction linkage.
3. **Fix 3** (throw on null invoice) — stops silent data loss; confirm retry semantics first.
4. **Fix 2** (canceled invoice) — needed alongside Fix 3 to avoid infinite retries on a
   genuinely canceled invoice.
5. **Fix 5** (transaction ID fallback lookup) — defensive; add logging first to confirm
   the mismatch actually occurs in production before widening the match.
6. **Fix 4** (external payment URL / PWA flow) — needs product decision before implementing.
