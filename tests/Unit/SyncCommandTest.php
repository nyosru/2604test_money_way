<?php

use PHPUnit\Framework\TestCase;

final class SyncCommandTest extends TestCase
{
    public function testInvoiceNumberInPurposeMatchesExactInvoiceToken(): void
    {
        self::assertTrue(
            SyncCommandTestProxy::callInvoiceNumberInPurpose(
                '1020',
                'Оплата по сч/ф 1020 от 19.02.2025 по договору № Б/Н'
            )
        );
    }

    public function testInvoiceNumberInPurposeDoesNotMatchSubstring(): void
    {
        self::assertFalse(
            SyncCommandTestProxy::callInvoiceNumberInPurpose(
                '1020',
                'Оплата по сч/ф 51020 от 19.02.2025'
            )
        );
    }

    public function testAttachToInvoiceOutLinksPaymentWhenInvoiceNumberMatchesPurpose(): void
    {
        $jsonApi = new MockJsonApi([
            $this->makeInvoiceOut('invoice-1', '1020', 150000),
        ]);

        $command = $this->makeCommand();
        $payments = new ah([
            $this->makePaymentIn('payment-1', 'Оплата по сч/ф 1020 от 19.02.2025', 150000),
        ]);

        $command->callAttachToInvoiceOut($payments, new MoyskladApp($jsonApi));

        self::assertCount(1, $jsonApi->sentEntities['paymentin'] ?? []);
        self::assertCount(1, $jsonApi->sentEntities['invoiceout'] ?? []);
        self::assertSame(
            'invoice-1',
            $jsonApi->sentEntities['paymentin'][0]['operations'][0]['meta']['href']
        );
        self::assertSame(
            'payment-1',
            $jsonApi->sentEntities['invoiceout'][0]['payments'][0]['meta']['href']
        );
    }

    public function testAttachToInvoiceOutDoesNotLinkOnlyByMatchingSum(): void
    {
        $jsonApi = new MockJsonApi([
            $this->makeInvoiceOut('invoice-1', '1020', 150000, '2025-02-19 00:00:00'),
        ]);

        $command = $this->makeCommand();
        $payments = new ah([
            $this->makePaymentIn('payment-1', 'Оплата по договору без номера счёта', 150000),
        ]);

        $command->callAttachToInvoiceOut($payments, new MoyskladApp($jsonApi));

        self::assertArrayNotHasKey('paymentin', $jsonApi->sentEntities);
        self::assertArrayNotHasKey('invoiceout', $jsonApi->sentEntities);
    }

    private function makeCommand(): SyncCommandTestProxy
    {
        $command = new SyncCommandTestProxy();
        $command->user = new ah([
            'settings' => [
                AttributeModel::TABLE_NAME => [
                    'paymentin' => [
                        'isAttachedToInvoice' => [
                            'name' => 'isAttachedToInvoice',
                            'value' => false,
                        ],
                    ],
                ],
            ],
        ]);

        return $command;
    }

    private function makeInvoiceOut(string $href, string $name, int $sum, string $moment = '2025-02-19 00:00:00'): array
    {
        return [
            'meta' => ['href' => $href],
            'name' => $name,
            'sum' => $sum,
            'moment' => $moment,
            'agent' => ['meta' => ['href' => 'agent-1']],
            'organizationAccount' => ['meta' => ['href' => 'account-1']],
            'organization' => ['meta' => ['href' => 'organization-1']],
        ];
    }

    private function makePaymentIn(string $href, string $purpose, int $sum): array
    {
        return [
            'meta' => ['href' => $href],
            'sum' => $sum,
            'paymentPurpose' => $purpose,
            'agent' => ['meta' => ['href' => 'agent-1']],
            'organizationAccount' => ['meta' => ['href' => 'account-1']],
            'organization' => ['meta' => ['href' => 'organization-1']],
        ];
    }
}

final class SyncCommandTestProxy extends SyncCommand
{
    public ah $user;

    public static function callInvoiceNumberInPurpose(string $invoiceName, string $paymentPurpose): bool
    {
        return parent::invoiceNumberInPurpose($invoiceName, $paymentPurpose);
    }

    public function callAttachToInvoiceOut(ah $paymentsIn, MoyskladApp $msApp): void
    {
        $this->attachToInvoiceOut($paymentsIn, $msApp);
    }
}
