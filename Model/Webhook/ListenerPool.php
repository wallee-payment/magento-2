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
namespace Wallee\Payment\Model\Webhook;

use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\ObjectManager\TMapFactory;

/**
 * Webhook listener pool.
 */
class ListenerPool implements ListenerPoolInterface
{

    /**
     *
     * @var ListenerInterface[]
     */
    private $listeners;

    /**
     *
     * @param TMapFactory $tmapFactory
     * @param array $listeners
     */
    public function __construct(TMapFactory $tmapFactory, array $listeners = [])
    {
        $this->listeners = $tmapFactory->create([
            'array' => $listeners,
            'type' => ListenerInterface::class
        ]);
    }

    /**
     * Retrieves listener.
     *
     * @param string $listenerCode
     * @return ListenerInterface
     * @throws NotFoundException
     */
    public function get($listenerCode)
    {
        if (! isset($this->listeners[$listenerCode])) {
            throw new NotFoundException(\__('Listener %1 does not exist.', $listenerCode));
        }

        return $this->listeners[$listenerCode];
    }
}