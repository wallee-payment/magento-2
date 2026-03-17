<?php

declare(strict_types=1);

namespace Wallee\Payment\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Magento\Framework\Filesystem\Driver\File;
use Wallee\PluginCore\Settings\Settings;

class Webhook extends Base
{
    /**
     * @var string
     */
    protected $fileName;

    /**
     * @param Settings $settings
     * @param File $filesystem
     * @param string|null $filePath
     * @param string|null $fileName
     */
    public function __construct(
        Settings $settings,
        File $filesystem,
        ?string $filePath = null,
        ?string $fileName = null
    ) {
        $this->loggerType = $settings->getLogLevel();

        // Call the parent constructor with its required arguments
        parent::__construct($filesystem, $filePath, $fileName);
    }
}
