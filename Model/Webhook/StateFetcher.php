<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook;

use Wallee\PluginCore\Webhook\DefaultStateFetcher;

/**
 * Extends plugin-core's DefaultStateFetcher.
 *
 * plugin-core ships as a plain Composer library (not a registered Magento
 * component), so its own classes can't be used directly as di.xml preference
 * targets — Magento's DI compiler only validates/compiles preferences whose
 * target lives in a registered module. This subclass lives in our module so
 * the preference target is compilable, without changing any behavior.
 */
class StateFetcher extends DefaultStateFetcher
{
}
