<?php

namespace Tests\Unit\Services;

use App\Models\Asset;
use App\Services\AssetService;
use Exception;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AssetServiceTest extends TestCase
{
    protected AssetService $assetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetService = new AssetService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createAssetMock(
        string $amount = '10.00000000',
        string $lockedAmount = '5.00000000'
    ): Asset {
        $asset = Mockery::mock(Asset::class)->makePartial();
        $asset->amount = $amount;
        $asset->locked_amount = $lockedAmount;
        $asset->shouldReceive('save')->andReturn(true)->byDefault();

        return $asset;
    }

    #[Test]
    public function get_total_amount_calculates_sum_correctly()
    {
        $asset = $this->createAssetMock('10.12345678', '5.87654322');

        $total = $asset->getTotalAmount();

        $this->assertEquals('16.00000000', $total);
    }

    #[Test]
    public function get_total_amount_handles_zero_values()
    {
        $asset = $this->createAssetMock('0.00000000', '0.00000000');

        $total = $asset->getTotalAmount();

        $this->assertEquals('0.00000000', $total);
    }

    #[Test]
    public function get_total_amount_handles_large_numbers()
    {
        $asset = $this->createAssetMock('999999.99999999', '1000000.00000001');

        $total = $asset->getTotalAmount();

        $this->assertEquals('2000000.00000000', $total);
    }

    #[Test]
    public function has_available_amount_returns_true_when_equal()
    {
        $asset = $this->createAssetMock();

        $result = $this->assetService->hasAvailableAmount($asset, '10.00000000');

        $this->assertTrue($result);
    }

    #[Test]
    public function has_available_amount_returns_true_when_sufficient()
    {
        $asset = $this->createAssetMock();

        $result = $this->assetService->hasAvailableAmount($asset, '9.99999999');

        $this->assertTrue($result);
    }

    #[Test]
    public function has_available_amount_returns_false_when_insufficient()
    {
        $asset = $this->createAssetMock();

        $result = $this->assetService->hasAvailableAmount($asset, '10.00000001');

        $this->assertFalse($result);
    }

    #[Test]
    public function has_available_amount_returns_false_when_zero_balance()
    {
        $asset = $this->createAssetMock('0.00000000');

        $result = $this->assetService->hasAvailableAmount($asset, '0.00000001');

        $this->assertFalse($result);
    }

    #[Test]
    public function add_amount_increases_available_amount_correctly()
    {
        $asset = $this->createAssetMock('10.50000000');
        $asset->shouldReceive('save')->once()->andReturn(true);

        $this->assetService->addAmount($asset, '3.12345678');

        $this->assertEquals('13.62345678', $asset->amount);
        $this->assertEquals('5.00000000', $asset->locked_amount);
    }

    #[Test]
    public function add_amount_handles_very_small_amounts()
    {
        $asset = $this->createAssetMock();
        $asset->shouldReceive('save')->once()->andReturn(true);

        $this->assetService->addAmount($asset, '0.00000001');

        $this->assertEquals('10.00000001', $asset->amount);
    }

    #[Test]
    public function add_amount_handles_large_amounts()
    {
        $asset = $this->createAssetMock();
        $asset->shouldReceive('save')->once()->andReturn(true);

        $this->assetService->addAmount($asset, '999990.00000000');

        $this->assertEquals('1000000.00000000', $asset->amount);
    }

    #[Test]
    public function add_amount_maintains_precision()
    {
        $asset = $this->createAssetMock('0.12345678', '0.00000000');
        $asset->shouldReceive('save')->once()->andReturn(true);

        $this->assetService->addAmount($asset, '0.87654321');

        $this->assertEquals('0.99999999', $asset->amount);
    }

    #[Test]
    public function lock_amount_moves_balance_from_available_to_locked()
    {
        $asset = $this->createAssetMock();
        $asset->shouldReceive('save')->once()->andReturn(true);

        $this->assetService->lockAmount($asset, '3.00000000');

        $this->assertEquals('7.00000000', $asset->amount);
        $this->assertEquals('8.00000000', $asset->locked_amount);
        $this->assertEquals('15.00000000', $asset->getTotalAmount());
    }

    #[Test]
    public function lock_amount_handles_locking_all_available_balance()
    {
        $asset = $this->createAssetMock();
        $asset->shouldReceive('save')->once()->andReturn(true);

        $this->assetService->lockAmount($asset, '10.00000000');

        $this->assertEquals('0.00000000', $asset->amount);
        $this->assertEquals('15.00000000', $asset->locked_amount);
    }

    #[Test]
    public function unlock_amount_moves_balance_from_locked_to_available()
    {
        $asset = $this->createAssetMock();
        $asset->shouldReceive('save')->once()->andReturn(true);

        $this->assetService->unlockAmount($asset, '2.00000000');

        $this->assertEquals('12.00000000', $asset->amount);
        $this->assertEquals('3.00000000', $asset->locked_amount);
        $this->assertEquals('15.00000000', $asset->getTotalAmount());
    }

    #[Test]
    public function unlock_amount_handles_unlocking_all_locked_balance()
    {
        $asset = $this->createAssetMock();
        $asset->shouldReceive('save')->once()->andReturn(true);

        $this->assetService->unlockAmount($asset, '5.00000000');

        $this->assertEquals('15.00000000', $asset->amount);
        $this->assertEquals('0.00000000', $asset->locked_amount);
    }

    #[Test]
    public function unlock_amount_throws_exception_on_insufficient_locked_amount()
    {
        $asset = $this->createAssetMock();
        $asset->shouldNotReceive('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient locked amount');

        $this->assetService->unlockAmount($asset, '5.00000001');
    }

    #[Test]
    public function unlock_amount_throws_exception_when_locked_amount_is_zero()
    {
        $asset = $this->createAssetMock('10.00000000', '0.00000000');
        $asset->shouldNotReceive('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient locked amount');

        $this->assetService->unlockAmount($asset, '0.00000001');
    }

    #[Test]
    public function deduct_locked_amount_decreases_locked_amount_correctly()
    {
        $asset = $this->createAssetMock('10.00000000', '5.00000000');
        $asset->shouldReceive('save')->once()->andReturn(true);

        $this->assetService->deductLockedAmount($asset, '1.50000000');

        $this->assertEquals('10.00000000', $asset->amount);
        $this->assertEquals('3.50000000', $asset->locked_amount);
        $this->assertEquals('13.50000000', $asset->getTotalAmount());
    }

    #[Test]
    public function deduct_locked_amount_handles_deducting_all_locked_balance()
    {
        $asset = $this->createAssetMock();
        $asset->shouldReceive('save')->once()->andReturn(true);

        $this->assetService->deductLockedAmount($asset, '5.00000000');

        $this->assertEquals('10.00000000', $asset->amount);
        $this->assertEquals('0.00000000', $asset->locked_amount);
    }

    #[Test]
    public function deduct_locked_amount_throws_exception_on_insufficient_locked_amount()
    {
        $asset = $this->createAssetMock();
        $asset->shouldNotReceive('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient locked amount');

        $this->assetService->deductLockedAmount($asset, '5.00000001');
    }

    #[Test]
    public function deduct_locked_amount_throws_exception_when_locked_amount_is_zero()
    {
        $asset = $this->createAssetMock('10.00000000', '0.00000000');
        $asset->shouldNotReceive('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient locked amount');

        $this->assetService->deductLockedAmount($asset, '0.00000001');
    }

    #[Test]
    public function operations_maintain_8_decimal_precision()
    {
        $asset = $this->createAssetMock('10.12345678', '5.87654322');
        $asset->shouldReceive('save')->times(3)->andReturn(true);

        // Add
        $this->assetService->addAmount($asset, '1.11111111');
        $this->assertEquals('11.23456789', $asset->amount);

        // Lock
        $this->assetService->lockAmount($asset, '0.23456789');
        $this->assertEquals('11.00000000', $asset->amount);
        $this->assertEquals('6.11111111', $asset->locked_amount);

        // Deduct
        $this->assetService->deductLockedAmount($asset, '6.11111111');
        $this->assertEquals('0.00000000', $asset->locked_amount);
    }

    #[Test]
    public function complex_operations_sequence_maintains_total_balance()
    {
        $asset = $this->createAssetMock('100.00000000', '50.00000000');
        $asset->shouldReceive('save')->times(5)->andReturn(true);

        $initialTotal = $asset->getTotalAmount();

        // Lock some
        $this->assetService->lockAmount($asset, '25.00000000');
        $this->assertEquals($initialTotal, $asset->getTotalAmount());

        // Unlock some
        $this->assetService->unlockAmount($asset, '10.00000000');
        $this->assertEquals($initialTotal, $asset->getTotalAmount());

        // Add more
        $this->assetService->addAmount($asset, '50.00000000');
        $this->assertEquals('200.00000000', $asset->getTotalAmount());

        // Deduct locked
        $this->assetService->deductLockedAmount($asset, '30.00000000');
        $this->assertEquals('170.00000000', $asset->getTotalAmount());

        // Lock remaining
        $this->assetService->lockAmount($asset, '135.00000000');
        $this->assertEquals('170.00000000', $asset->getTotalAmount());
    }
}
