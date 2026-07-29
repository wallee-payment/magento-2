<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee_Payment
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)

 */
declare(strict_types=1);

namespace Wallee\Payment\Model\PluginCore;

use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote;

/**
 * Builds {@see QuoteTransactionPersistence} instances bound to a specific quote
 * and space, so callers do not have to thread the resource connection through
 * every call site.
 */
class QuoteTransactionPersistenceFactory
{
    /**
     *
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource,
    ) {
    }

    /**
     * Builds a persistence strategy for the given quote/space pair.
     *
     * @param Quote $quote
     * @param int $spaceId
     * @return QuoteTransactionPersistence
     */
    public function create(Quote $quote, int $spaceId): QuoteTransactionPersistence
    {
        return new QuoteTransactionPersistence($quote, $spaceId, $this->resource);
    }
}
