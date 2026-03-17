<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Monolog\Logger;

class LogLevel implements OptionSourceInterface
{
    /**
     * Return log level options.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                // Production default
                'value' => Logger::INFO,
                'label' => __('Info')
            ],
            [
                // For development and debugging
                'value' => Logger::DEBUG,
                'label' => __('Debug')
            ]
        ];
    }
}
