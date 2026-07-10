<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sicil sorgulama (tahakkuk) -> borç seçimi -> banka tahsilatı akışını
 * BELSIS_MOCK demo verisiyle uçtan uca doğrular (gerçek Belsis ağı gerekmez).
 */
class KioskTahakkukTahsilatFlowTest extends TestCase
{
    private const DEMO_SICIL = '89874';

    public function test_citizen_endpoint_returns_demo_data_for_mock_sicil(): void
    {
        $response = $this->getJson('/api/kiosk/citizen/'.self::DEMO_SICIL);

        $response->assertOk()->assertJson([
            'identityNo' => self::DEMO_SICIL,
            'sicilNo'    => self::DEMO_SICIL,
        ])->assertJsonStructure(['identityNo', 'fullName', 'sicilNo']);
    }

    public function test_debts_endpoint_returns_demo_debts_for_mock_sicil(): void
    {
        $response = $this->getJson('/api/kiosk/debts/'.self::DEMO_SICIL);

        $response->assertOk()
            ->assertJsonCount(2, 'debts')
            ->assertJsonStructure([
                'debts' => [['id', 'type', 'period', 'amount', 'dueDate', 'meta']],
            ]);
    }

    public function test_payment_methods_endpoint_returns_demo_methods(): void
    {
        $response = $this->getJson('/api/kiosk/payment-methods');

        $response->assertOk()->assertJsonStructure([
            'methods' => [['id', 'name']],
        ]);
    }

    public function test_sicil_no_with_invalid_format_is_rejected(): void
    {
        $this->getJson('/api/kiosk/citizen/abc123')->assertNotFound();
        $this->getJson('/api/kiosk/citizen/123456789012')->assertNotFound();
    }

    public function test_full_bank_collection_flow_initiate_then_confirm(): void
    {
        $debts = $this->getJson('/api/kiosk/debts/'.self::DEMO_SICIL)->json('debts');
        $debtIds = array_column($debts, 'id');

        $initiate = $this->postJson('/api/kiosk/payment/bank', [
            'identityNo' => self::DEMO_SICIL,
            'debtIds'    => $debtIds,
        ]);

        $initiate->assertOk()->assertJsonStructure([
            'transactionId', 'total', 'status', 'paymentMethod',
        ])->assertJson(['status' => 'pending']);

        $transactionId = $initiate->json('transactionId');
        $expectedTotal = array_sum(array_column($debts, 'amount'));
        $this->assertSame($expectedTotal, $initiate->json('total'));

        $confirm = $this->postJson('/api/kiosk/payment/'.$transactionId.'/confirm', [
            'identityNo' => self::DEMO_SICIL,
            'debtIds'    => $debtIds,
        ]);

        $confirm->assertOk()->assertJson([
            'transactionId' => $transactionId,
            'status'        => 'completed',
        ])->assertJsonStructure(['receiptNo', 'makbuzID', 'seriNo', 'makbuzNo', 'receipt']);
    }

    public function test_initiate_payment_requires_at_least_one_debt(): void
    {
        $this->postJson('/api/kiosk/payment/bank', [
            'identityNo' => self::DEMO_SICIL,
            'debtIds'    => [],
        ])->assertUnprocessable();
    }

    public function test_initiate_payment_rejects_unknown_debt_id(): void
    {
        $this->postJson('/api/kiosk/payment/bank', [
            'identityNo' => self::DEMO_SICIL,
            'debtIds'    => ['does-not-exist'],
        ])->assertNotFound();
    }
}
