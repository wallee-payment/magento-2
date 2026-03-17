<?php

declare(strict_types=1);

namespace Wallee\Payment\Logger;

use Wallee\PluginCore\Log\LoggerInterface as PluginCoreLoggerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * This class adapts Magento's PSR-3 logger to the interface required by plugin-core.
 */
class PluginCoreLoggerAdapter implements PluginCoreLoggerInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private PsrLoggerInterface $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(PsrLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    /**
     * @inheritdoc
     */
    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    /**
     * @inheritdoc
     */
    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    /**
     * @inheritdoc
     */
    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * @inheritdoc
     */
    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * @inheritdoc
     */
    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    /**
     * @inheritdoc
     */
    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * @inheritdoc
     */
    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * @inheritdoc
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
    /**
     * Not used. Returning an empty string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return '';
    }
}
