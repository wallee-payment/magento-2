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
namespace Wallee\Payment\Model\ResourceModel;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Wallee\Payment\Api\Data\RefundJobInterface;
use Wallee\PluginCore\Refund\LineItem\RefundLineItem;
use Wallee\PluginCore\Refund\LineItem\RefundLineItemCollection;
use Wallee\PluginCore\Refund\RefundContext;
use Wallee\PluginCore\Refund\Type as CoreType;

/**
 * Transaction Info Resource Model
 */
class RefundJob extends AbstractDb
{

    /**
     *
     * @var string
     */
    protected $_eventPrefix = 'wallee_payment_refund_job_resource';

    /**
     *
     * @var array<string, mixed>
     */
    protected $_serializableFields = [
        'refund' => [
            null,
            null
        ]
    ];

    /**
     * Model initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('wallee_payment_refund_job', 'entity_id');
    }

    /**
     * Serialize the refund context before saving.
     *
     * @param DataObject $object
     * @param string $field
     * @param mixed $defaultValue
     * @param bool $unsetEmpty
     * @return $this
     */
    protected function _serializeField(DataObject $object, $field, $defaultValue = null, $unsetEmpty = false)
    {
        if ($field == RefundJobInterface::REFUND) {
            $value = $object->getData($field);
            if (empty($value) && $unsetEmpty) {
                $object->unsetData($field);
            } else {
                $object->setData(
                    $field,
                    $value instanceof RefundContext ? \json_encode($value) : $defaultValue
                );
            }

            return $this;
        } else {
            return parent::_serializeField($object, $field, $defaultValue, $unsetEmpty);
        }
    }

    /**
     * Unserialize the refund context after loading.
     *
     * @param DataObject $object
     * @param string $field
     * @param mixed $defaultValue
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function _unserializeField(DataObject $object, $field, $defaultValue = null)
    {
        if ($field == RefundJobInterface::REFUND) {
            $value = $object->getData($field);
            if ($value) {
                $data = \json_decode((string) $value, true);
                if (json_last_error() !== JSON_ERROR_NONE || ! \is_array($data)) {
                    throw new \InvalidArgumentException('Unable to unserialize value.');
                }
                // Legacy fallback: jobs persisted before the RefundLineItem migration
                // stored 'quantity'/'amount' keys instead of 'returnedQuantity'/
                // 'unitPriceReduction'; missing keys default to 0 rather than failing
                // to deserialize an in-flight retry job.
                $lineItems = new RefundLineItemCollection(
                    ...array_map(
                        static fn (array $item): RefundLineItem => new RefundLineItem(
                            uniqueId: (string) ($item['uniqueId'] ?? ''),
                            returnedQuantity: (float) ($item['returnedQuantity'] ?? $item['quantity'] ?? 0),
                            unitPriceReduction: (float) ($item['unitPriceReduction'] ?? $item['amount'] ?? 0),
                        ),
                        $data['lineItems'] ?? []
                    )
                );
                $object->setData($field, new RefundContext(
                    transactionId: (int) $data['transactionId'],
                    amount: (float) $data['amount'],
                    merchantReference: (string) $data['merchantReference'],
                    type: CoreType::from($data['type']),
                    lineItems: $lineItems,
                    externalId: $data['externalId'] ?? null
                ));
            } else {
                $object->setData($field, $defaultValue);
            }
        } else {
            parent::_unserializeField($object, $field, $defaultValue);
        }
    }
}
