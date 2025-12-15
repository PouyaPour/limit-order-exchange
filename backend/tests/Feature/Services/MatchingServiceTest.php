<?php

namespace Tests\Feature\Services;

use App\Enums\OrderSideEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\SymbolEnum;
use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Services\MatchingService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatchingService $matchingService;
    private User $buyer;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        config(['order.commission_rate' => '0.001']);

        $this->matchingService = app(MatchingService::class);

        $this->buyer = User::factory()->create([
            'balance' => '100000.00000000'
        ]);

        $this->seller = User::factory()->create([
            'balance' => '50000.00000000'
        ]);
    }

    #[Test]
    public function it_matches_buy_order_with_sell_order_successfully()
    {
        Event::fake([OrderMatched::class]);

        $sellerAsset = Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.50000000',
        ]);

        $sellOrder = Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '25550.00000000',
        ]);

        $trade = $this->matchingService->matchOrder($buyOrder);

        $this->assertInstanceOf(Trade::class, $trade);

        $this->assertEquals($buyOrder->id, $trade->buy_order_id);
        $this->assertEquals($sellOrder->id, $trade->sell_order_id);
        $this->assertEquals(SymbolEnum::BTC->value, $trade->symbol->value);
        $this->assertEquals('50000.00000000', $trade->price);
        $this->assertEquals('0.50000000', $trade->amount);

        $this->assertEquals('25000.00000000', $trade->total_value);

        $this->assertEquals('25.00000000', $trade->commission);

        $this->assertEquals(OrderStatusEnum::FILLED, $buyOrder->fresh()->status);
        $this->assertEquals(OrderStatusEnum::FILLED, $sellOrder->fresh()->status);

        $this->seller->refresh();
        $expectedSellerBalance = bcadd('50000.00000000', '24975.00000000', 8);
        $this->assertEquals($expectedSellerBalance, $this->seller->balance);

        $sellerAsset->refresh();
        $this->assertEquals('0.00000000', $sellerAsset->locked_amount);

        $buyerAsset = Asset::where('user_id', $this->buyer->id)
            ->where('symbol', SymbolEnum::BTC)
            ->first();
        $this->assertNotNull($buyerAsset);
        $this->assertEquals('0.50000000', $buyerAsset->amount);

        Event::assertDispatched(OrderMatched::class, 2);
    }

    #[Test]
    public function it_matches_sell_order_with_buy_order_successfully()
    {
        Event::fake([OrderMatched::class]);

        Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.50000000',
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '25550.00000000',
        ]);

        $sellOrder = Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $trade = $this->matchingService->matchOrder($sellOrder);

        $this->assertInstanceOf(Trade::class, $trade);

        $this->assertEquals('51000.00000000', $trade->price);

        $this->assertEquals(OrderStatusEnum::FILLED, $buyOrder->fresh()->status);
        $this->assertEquals(OrderStatusEnum::FILLED, $sellOrder->fresh()->status);

        Event::assertDispatched(OrderMatched::class, 2);
    }

    #[Test]
    public function it_returns_null_when_no_matching_order_exists()
    {
        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
        ]);

        $trade = $this->matchingService->matchOrder($buyOrder);

        $this->assertNull($trade);
        $this->assertEquals(OrderStatusEnum::OPEN, $buyOrder->fresh()->status);
    }

    #[Test]
    public function it_returns_null_when_order_is_not_open()
    {
        $order = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'status' => OrderStatusEnum::FILLED,
        ]);

        $trade = $this->matchingService->matchOrder($order);

        $this->assertNull($trade);
    }

    #[Test]
    public function it_does_not_match_orders_from_same_user()
    {
        Asset::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.50000000',
        ]);

        Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
        ]);

        $trade = $this->matchingService->matchOrder($buyOrder);

        $this->assertNull($trade);
    }

    #[Test]
    public function it_matches_buy_order_with_lowest_sell_price()
    {
        Event::fake();

        Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '2.00000000',
            'locked_amount' => '1.00000000',
        ]);

        Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '52000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $lowPriceSell = Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '53000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '26650.00000000',
        ]);

        $trade = $this->matchingService->matchOrder($buyOrder);

        $this->assertEquals($lowPriceSell->id, $trade->sell_order_id);
        $this->assertEquals('50000.00000000', $trade->price);
    }

    #[Test]
    public function it_matches_sell_order_with_highest_buy_price()
    {
        Event::fake();

        Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.50000000',
        ]);

        Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '25025.00000000',
        ]);

        $highPriceBuy = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '52000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '26026.00000000',
        ]);

        $sellOrder = Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '49000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $trade = $this->matchingService->matchOrder($sellOrder);

        $this->assertEquals($highPriceBuy->id, $trade->buy_order_id);
        $this->assertEquals('52000.00000000', $trade->price);
    }

    #[Test]
    public function it_respects_fifo_for_same_price_orders()
    {
        Event::fake();

        Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '2.00000000',
            'locked_amount' => '1.00000000',
        ]);

        $oldOrder = Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
            'created_at' => now()->subHours(2),
        ]);

        Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
            'created_at' => now()->subHour(),
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '25550.00000000',
        ]);

        $trade = $this->matchingService->matchOrder($buyOrder);

        $this->assertEquals($oldOrder->id, $trade->sell_order_id);
    }

    #[Test]
    public function it_does_not_match_when_buy_price_is_lower_than_sell_price()
    {
        Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.50000000',
        ]);

        Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '52000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
        ]);

        $trade = $this->matchingService->matchOrder($buyOrder);

        $this->assertNull($trade);
    }

    #[Test]
    public function it_throws_exception_when_buyer_has_insufficient_locked_balance()
    {
        Event::fake();

        Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.50000000',
        ]);

        Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '1000.00000000',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient locked balance for buyer');

        $this->matchingService->matchOrder($buyOrder);
    }

    #[Test]
    public function it_throws_exception_when_seller_asset_not_found()
    {
        Event::fake();

        Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '25550.00000000',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Seller asset not found');

        $this->matchingService->matchOrder($buyOrder);
    }

    #[Test]
    public function it_creates_buyer_asset_if_not_exists()
    {
        Event::fake();

        Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.50000000',
        ]);

        Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '25550.00000000',
        ]);

        $this->assertDatabaseMissing('assets', [
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC->value,
        ]);

        $this->matchingService->matchOrder($buyOrder);

        $buyerAsset = Asset::where('user_id', $this->buyer->id)
            ->where('symbol', SymbolEnum::BTC)
            ->first();

        $this->assertNotNull($buyerAsset);
        $this->assertEquals('0.50000000', $buyerAsset->amount);
        $this->assertEquals('0.00000000', $buyerAsset->locked_amount);
    }

    #[Test]
    public function it_calculates_commission_correctly()
    {
        Event::fake();

        config(['order.commission_rate' => '0.002']);

        Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.50000000',
        ]);

        Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '26000.00000000',
        ]);

        $trade = $this->matchingService->matchOrder($buyOrder);

        $this->assertEquals('25000.00000000', $trade->total_value);
        $this->assertEquals('50.00000000', $trade->commission);

        $this->seller->refresh();
        $expectedSellerBalance = bcadd('50000.00000000', '24950.00000000', 8);
        $this->assertEquals($expectedSellerBalance, $this->seller->balance);
    }

    #[Test]
    public function it_only_matches_orders_with_same_symbol()
    {
        Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::ETH,
            'amount' => '10.00000000',
            'locked_amount' => '5.00000000',
        ]);

        Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::ETH,
            'side' => OrderSideEnum::SELL,
            'price' => '3000.00000000',
            'amount' => '5.00000000',
            'status' => OrderStatusEnum::OPEN,
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
        ]);

        $trade = $this->matchingService->matchOrder($buyOrder);

        $this->assertNull($trade);
    }

    #[Test]
    public function it_broadcasts_order_matched_event_to_both_users()
    {
        Event::fake([OrderMatched::class]);

        Asset::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.50000000',
        ]);

        $sellOrder = Order::factory()->create([
            'user_id' => $this->seller->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $buyOrder = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '25550.00000000',
        ]);

        $trade = $this->matchingService->matchOrder($buyOrder);

        Event::assertDispatched(OrderMatched::class, function ($event) use ($trade, $buyOrder) {
            return $event->trade->id === $trade->id && $event->userId === $buyOrder->user_id;
        });

        Event::assertDispatched(OrderMatched::class, function ($event) use ($trade, $sellOrder) {
            return $event->trade->id === $trade->id && $event->userId === $sellOrder->user_id;
        });
    }
}
