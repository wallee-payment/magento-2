<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Model\Provider;

use Magento\Framework\Cache\FrontendInterface;

/**
 * Abstract implementation of a provider.
 */
abstract class AbstractProvider
{

    /**
     *
     * @var FrontendInterface
     */
    private $cache;

    /**
     * Cache key.
     *
     * @var string
     */
    private $cacheKey;

    /**
     * Data.
     *
     * @var array
     */
    private $data;

    /**
     *
     * @param FrontendInterface $cache
     * @param string $cacheKey
     */
    public function __construct(FrontendInterface $cache, $cacheKey)
    {
        $this->cache = $cache;
        $this->cacheKey = $cacheKey;
    }

    /**
     * Gets a single entry by its id.
     *
     * @param string $id
     * @return mixed
     */
    public function find($id)
    {
        if ($this->data == null) {
            $this->loadData();
        }

        if (isset($this->data[$id])) {
            return $this->data[$id];
        } else {
            return false;
        }
    }

    /**
     * Gets all entries.
     *
     * @return array
     */
    public function getAll()
    {
        if ($this->data == null) {
            $this->loadData();
        }
        return $this->data;
    }

    /**
     * Fetches the data from the remote server.
     */
    abstract protected function fetchData();

    /**
     * Gets the id of the given entry.
     *
     * @param mixed $entry
     * @return int
     */
    abstract protected function getId($entry);

    private function loadData()
    {
        $cachedData = $this->cache->load($this->cacheKey);
        if ($cachedData) {
            $this->data = \unserialize($cachedData);
        } else {
            $this->data = [];
            foreach ($this->fetchData() as $entry) {
                $this->data[$this->getId($entry)] = $entry;
            }
            $this->cache->save(\serialize($this->data), $this->cacheKey);
        }
    }
}