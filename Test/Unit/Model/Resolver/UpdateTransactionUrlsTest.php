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
namespace Wallee\Payment\Test\Unit\Model\Resolver;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session;
use Magento\CustomerGraphQl\Model\Customer\GetCustomer;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\TransactionInfoManagementInterface;
use Wallee\Payment\Model\Resolver\UpdateTransactionUrls;
use Wallee\Payment\Model\Service\Quote\TransactionService;
use Wallee\PluginCore\Transaction\Transaction as CoreTransaction;

class UpdateTransactionUrlsTest extends TestCase
{
    private const SUCCESS_URL = 'https://example.com/success';
    private const FAILURE_URL = 'https://example.com/failure';

    /**
     * @var TransactionService|MockObject
     */
    private $transactionService;

    /**
     * @var TransactionInfoManagementInterface|MockObject
     */
    private $transactionInfoManagement;

    /**
     * @var Quote|\PHPUnit\Framework\MockObject\Stub
     */
    private $quote;

    /**
     * @var UpdateTransactionUrls
     */
    private $resolver;

    /**
     * @var bool
     */
    private $hasReservedOrderId = false;

    /**
     * @var array<string, mixed>
     */
    private $quoteData = ['getWalleeSpaceId' => 1, 'getWalleeTransactionId' => 2];

    /**
     * @var CheckoutSession|\PHPUnit\Framework\MockObject\Stub
     */
    private $checkoutSession;

    protected function setUp(): void
    {
        $this->quote = $this->createStub(Quote::class);
        $this->quote->method('__call')->willReturnCallback(
            function (string $method) {
                return $this->quoteData[$method]
                    ?? ($method === 'hasReservedOrderId' ? $this->hasReservedOrderId : null);
            }
        );

        $this->checkoutSession = $this->createStub(CheckoutSession::class);
        $this->checkoutSession->method('getQuote')->willReturn($this->quote);

        $this->transactionService = $this->createMock(TransactionService::class);
        $this->transactionInfoManagement = $this->createMock(TransactionInfoManagementInterface::class);

        $this->resolver = $this->createResolver();
    }

    public function testUsesQuoteTransactionAndFallsBackToTransactionIdAsOrderId(): void
    {
        $transaction = $this->createTransaction();
        $this->quote->method('getReservedOrderId')->willReturn(null);
        $this->transactionService->expects($this->once())
            ->method('getTransactionByQuote')
            ->with($this->quote)
            ->willReturn($transaction);
        $this->transactionInfoManagement->expects($this->once())
            ->method('setRedirectUrls')
            ->with($transaction, $transaction->id, self::SUCCESS_URL, self::FAILURE_URL);

        $this->assertSame(['result' => 'OK'], $this->resolve());
    }

    public function testKeepsReservedOrderId(): void
    {
        $transaction = $this->createTransaction();
        $this->quote->method('getReservedOrderId')->willReturn('000000123');
        $this->hasReservedOrderId = true;
        $this->transactionService->expects($this->once())->method('getTransactionByQuote')->willReturn($transaction);
        $this->transactionInfoManagement->expects($this->once())
            ->method('setRedirectUrls')
            ->with($transaction, '000000123', self::SUCCESS_URL, self::FAILURE_URL);

        $this->assertSame(['result' => 'OK'], $this->resolve());
    }

    public function testResolvesTransactionForCartQuoteNotSessionQuoteWhenQuoteHasNoTransactionIds(): void
    {
        $this->quoteData = [];
        $sessionQuote = $this->createStub(Quote::class);
        $this->checkoutSession = $this->createStub(CheckoutSession::class);
        $this->checkoutSession->method('getQuote')->willReturn($sessionQuote);
        $this->resolver = $this->createResolver();
        $transaction = $this->createTransaction();
        $this->quote->method('getReservedOrderId')->willReturn(null);
        $this->transactionService->expects($this->once())
            ->method('getJavaScriptUrl')
            ->with($this->quote)
            ->willReturn('');
        $this->transactionService->expects($this->once())
            ->method('getTransactionByQuote')
            ->with($this->identicalTo($this->quote))
            ->willReturn($transaction);
        $this->transactionInfoManagement->expects($this->once())->method('setRedirectUrls');

        $this->assertSame(['result' => 'OK'], $this->resolve());
    }

    private function createResolver(): UpdateTransactionUrls
    {
        $maskedQuoteIdToQuoteId = $this->createStub(MaskedQuoteIdToQuoteIdInterface::class);
        $maskedQuoteIdToQuoteId->method('execute')->willReturn(42);

        $cartRepository = $this->createStub(CartRepositoryInterface::class);
        $cartRepository->method('get')->willReturn($this->quote);

        return new UpdateTransactionUrls(
            $this->createStub(Session::class),
            $this->checkoutSession,
            $this->createStub(GetCustomer::class),
            $maskedQuoteIdToQuoteId,
            $cartRepository,
            $this->transactionService,
            $this->transactionInfoManagement,
            $this->createStub(LoggerInterface::class)
        );
    }

    private function createTransaction(): CoreTransaction
    {
        $transaction = new CoreTransaction();
        $transaction->id = 99;
        $transaction->spaceId = 1;
        return $transaction;
    }

    private function resolve(): array
    {
        return $this->resolver->resolve(
            $this->createStub(Field::class),
            null,
            $this->createStub(ResolveInfo::class),
            null,
            ['input' => [
                'cart_id' => 'masked',
                'success_url' => self::SUCCESS_URL,
                'failure_url' => self::FAILURE_URL,
            ]]
        );
    }
}
