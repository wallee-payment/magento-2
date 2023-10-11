<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Api;

use Wallee\Payment\Api\Data\TokenInfoInterface;

/**
 * Token info management interface.
 *
 * @api
 */
interface TokenInfoManagementInterface
{

    /**
     * Fetches the token version's latest state from wallee and updates the stored information.
     *
     * @param int $spaceId
     * @param int $tokenVersionId
     * @return void
     */
    public function updateTokenVersion($spaceId, $tokenVersionId);

    /**
     * Fetches the token's latest state from wallee and updates the stored information.
     *
     * @param int $spaceId
     * @param int $tokenId
     * @return void
     */
    public function updateToken($spaceId, $tokenId);

    /**
     * Deletes the token on wallee.
     *
     * @param TokenInfoInterface $token
     * @return void
     */
    public function deleteToken(TokenInfoInterface $token);
}