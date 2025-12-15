<?php

namespace Tests\Feature\Services;

use App\Enums\OrderSideEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\SymbolEnum;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Jobs\ProcessOrderMatching;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = app(OrderService::class);
        $this->user = User::factory()->create([
            'balance' => '10000.00000000'
        ]);
    }

   #[Test]
    public function it_creates_buy_order_successfully()
    {
        Event::fake([OrderCreated::class]);

        $order = $this->orderService->createOrder(
            user: $this->user,
            symbol: SymbolEnum::BTC,
            side: OrderSideEnum::BUY,
            price: '50000.00000000',
            amount: '0.10000000'
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $this->user->id,
            'symbol' => SymbolEnum::BTC->value,
            'side' => OrderSideEnum::BUY->value,
            'price' => '50000.00000000',
            'amount' => '0.10000000',
            'status' => OrderStatusEnum::OPEN->value,
        ]);

        $totalValue = bcmul('50000.00000000', '0.10000000', 8);
        $commission = bcmul($totalValue, config('order.commission_rate'), 8);
        $requiredBalance = bcadd($totalValue, $commission, 8);

        $this->user->refresh();
        $expectedBalance = bcsub('10000.00000000', $requiredBalance, 8);
        $this->assertEquals($expectedBalance, $this->user->balance);

        Event::assertDispatched(OrderCreated::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

   #[Test]
    public function it_fails_to_create_buy_order_with_insufficient_balance()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->orderService->createOrder(
            user: $this->user,
            symbol: SymbolEnum::BTC,
            side: OrderSideEnum::BUY,
            price: '100000.00000000',
            amount: '1.00000000'
        );
    }

   #[Test]
    public function it_creates_sell_order_successfully()
    {
        Event::fake([OrderCreated::class]);

        $asset = Asset::factory()->create([
            'user_id' => $this->user->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.00000000',
        ]);

        $order = $this->orderService->createOrder(
            user: $this->user,
            symbol: SymbolEnum::BTC,
            side: OrderSideEnum::SELL,
            price: '50000.00000000',
            amount: '0.50000000'
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $this->user->id,
            'symbol' => SymbolEnum::BTC->value,
            'side' => OrderSideEnum::SELL->value,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN->value,
            'locked_balance' => '0.50000000',
        ]);

        $asset->refresh();
        $this->assertEquals('0.50000000', $asset->locked_amount);

        Event::assertDispatched(OrderCreated::class);
    }

   #[Test]
    public function it_fails_to_create_sell_order_without_asset()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient asset amount');

        $this->orderService->createOrder(
            user: $this->user,
            symbol: SymbolEnum::BTC,
            side: OrderSideEnum::SELL,
            price: '50000.00000000',
            amount: '0.50000000'
        );
    }

   #[Test]
    public function it_fails_to_create_sell_order_with_insufficient_asset_amount()
    {
        Asset::factory()->create([
            'user_id' => $this->user->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '0.10000000',
            'locked_amount' => '0.00000000',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient asset amount');

        $this->orderService->createOrder(
            user: $this->user,
            symbol: SymbolEnum::BTC,
            side: OrderSideEnum::SELL,
            price: '50000.00000000',
            amount: '0.50000000'
        );
    }

   #[Test]
    public function it_cancels_buy_order_successfully()
    {
        Event::fake([OrderCancelled::class]);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'price' => '50000.00000000',
            'amount' => '0.10000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '5000.00000000',
        ]);

        $initialBalance = $this->user->balance;

        $cancelledOrder = $this->orderService->cancelOrder($order, $this->user);

        $this->assertEquals(OrderStatusEnum::CANCELLED, $cancelledOrder->status);

        $this->user->refresh();
        $expectedBalance = bcadd($initialBalance, '5000.00000000', 8);
        $this->assertEquals($expectedBalance, $this->user->balance);

        Event::assertDispatched(OrderCancelled::class);
    }

   #[Test]
    public function it_cancels_sell_order_successfully()
    {
        Event::fake([OrderCancelled::class]);

        $asset = Asset::factory()->create([
            'user_id' => $this->user->id,
            'symbol' => SymbolEnum::BTC,
            'amount' => '1.00000000',
            'locked_amount' => '0.50000000',
        ]);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => OrderStatusEnum::OPEN,
            'locked_balance' => '0.50000000',
        ]);

        $cancelledOrder = $this->orderService->cancelOrder($order, $this->user);

        $this->assertEquals(OrderStatusEnum::CANCELLED, $cancelledOrder->status);

        $asset->refresh();
        $this->assertEquals('0.00000000', $asset->locked_amount);

        Event::assertDispatched(OrderCancelled::class);
    }

   #[Test]
    public function it_fails_to_cancel_order_by_unauthorized_user()
    {
        $otherUser = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatusEnum::OPEN,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unauthorized to cancel this order');

        $this->orderService->cancelOrder($order, $otherUser);
    }

   #[Test]
    public function it_fails_to_cancel_filled_order()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatusEnum::FILLED,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Order cannot be cancelled');

        $this->orderService->cancelOrder($order, $this->user);
    }

   #[Test]
    public function it_fails_to_cancel_already_cancelled_order()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatusEnum::CANCELLED,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Order cannot be cancelled');

        $this->orderService->cancelOrder($order, $this->user);
    }

   #[Test]
    public function it_retrieves_orderbook_correctly()
    {
        Order::factory()->count(3)->create([
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'status' => OrderStatusEnum::OPEN,
            'price' => '50000.00000000',
        ]);

        Order::factory()->count(3)->create([
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'status' => OrderStatusEnum::OPEN,
            'price' => '51000.00000000',
        ]);

        Order::factory()->create([
            'symbol' => SymbolEnum::ETH,
            'side' => OrderSideEnum::BUY,
            'status' => OrderStatusEnum::OPEN,
        ]);

        $orderbook = $this->orderService->getOrderbook(SymbolEnum::BTC);

        $this->assertCount(3, $orderbook['buy_orders']);
        $this->assertCount(3, $orderbook['sell_orders']);

        $buyPrices = $orderbook['buy_orders']->pluck('price')->toArray();
        $sortedBuyPrices = $buyPrices;
        rsort($sortedBuyPrices);
        $this->assertEquals($sortedBuyPrices, $buyPrices);

        $sellPrices = $orderbook['sell_orders']->pluck('price')->toArray();
        $sortedSellPrices = $sellPrices;
        sort($sortedSellPrices);
        $this->assertEquals($sortedSellPrices, $sellPrices);
    }

   #[Test]
    public function it_limits_orderbook_to_20_orders_per_side()
    {
        Order::factory()->count(25)->create([
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'status' => OrderStatusEnum::OPEN,
        ]);

        Order::factory()->count(25)->create([
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::SELL,
            'status' => OrderStatusEnum::OPEN,
        ]);

        $orderbook = $this->orderService->getOrderbook(SymbolEnum::BTC);

        $this->assertCount(20, $orderbook['buy_orders']);
        $this->assertCount(20, $orderbook['sell_orders']);
    }

   #[Test]
    public function it_retrieves_user_orders()
    {
        Order::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'symbol' => SymbolEnum::BTC,
        ]);

        Order::factory()->count(2)->create([
            'symbol' => SymbolEnum::BTC,
        ]);

        $orders = $this->orderService->getUserOrders($this->user);

        $this->assertCount(3, $orders);
        $this->assertTrue($orders->every(fn($order) => $order->user_id === $this->user->id));
    }

   #[Test]
    public function it_filters_user_orders_by_symbol()
    {
        Order::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'symbol' => SymbolEnum::BTC,
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'symbol' => SymbolEnum::ETH,
        ]);

        $orders = $this->orderService->getUserOrders($this->user, SymbolEnum::BTC);

        $this->assertCount(2, $orders);
        $this->assertTrue($orders->every(fn($order) => $order->symbol === SymbolEnum::BTC));
    }

   #[Test]
    public function it_filters_user_orders_by_status()
    {
        Order::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => OrderStatusEnum::OPEN,
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatusEnum::FILLED,
        ]);

        $orders = $this->orderService->getUserOrders($this->user, status: OrderStatusEnum::OPEN);

        $this->assertCount(2, $orders);
        $this->assertTrue($orders->every(fn($order) => $order->status === OrderStatusEnum::OPEN));
    }

   #[Test]
    public function it_dispatches_order_matching_job_to_high_queue_for_large_orders()
    {
        Queue::fake();

        $order = Order::factory()->create([
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $this->orderService->processMatching($order);

        Queue::assertPushed(ProcessOrderMatching::class, function ($job) use ($order) {
            return $job->queue === 'high';
        });
    }

   #[Test]
    public function it_dispatches_order_matching_job_to_default_queue_for_small_orders()
    {
        Queue::fake();

        $order = Order::factory()->create([
            'price' => '50000.00000000',
            'amount' => '0.10000000',
        ]);

        $this->orderService->processMatching($order);

        Queue::assertPushed(ProcessOrderMatching::class, function ($job) use ($order) {
            return $job->queue === 'default';
        });
    }

   #[Test]
    public function it_marks_order_as_filled()
    {
        $order = Order::factory()->create([
            'status' => OrderStatusEnum::OPEN,
        ]);

        $this->orderService->markAsFilled($order);

        $order->refresh();
        $this->assertEquals(OrderStatusEnum::FILLED, $order->status);
    }

   #[Test]
    public function it_marks_order_as_cancelled()
    {
        $order = Order::factory()->create([
            'status' => OrderStatusEnum::OPEN,
        ]);

        $this->orderService->markAsCancelled($order);

        $order->refresh();
        $this->assertEquals(OrderStatusEnum::CANCELLED, $order->status);
    }

   #[Test]
   public function it_handles_concurrent_order_cancellation()
   {
       $order = Order::factory()->create([
           'user_id' => $this->user->id,
           'status' => OrderStatusEnum::OPEN,
           'side' => OrderSideEnum::BUY,
           'locked_balance' => '5000.00000000',
       ]);

       $order->status = OrderStatusEnum::FILLED;
       $order->save();

       $this->expectException(Exception::class);
       $this->expectExceptionMessage('Order cannot be cancelled');

       $this->orderService->cancelOrder($order->fresh(), $this->user);
   }

   #[Test]
    public function it_calculates_locked_balance_correctly_for_buy_orders()
    {
        config(['order.commission_rate' => '0.001']);

        $order = $this->orderService->createOrder(
            user: $this->user,
            symbol: SymbolEnum::BTC,
            side: OrderSideEnum::BUY,
            price: '50000.00000000',
            amount: '0.10000000'
        );

        $expectedLockedBalance = '5005.00000000';

        $this->assertEquals($expectedLockedBalance, $order->locked_balance);
    }

   #[Test]
    public function it_orders_by_price_and_time_in_orderbook()
    {
        $oldOrder = Order::factory()->create([
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'status' => OrderStatusEnum::OPEN,
            'price' => '50000.00000000',
            'created_at' => now()->subHours(2),
        ]);

        Order::factory()->create([
            'symbol' => SymbolEnum::BTC,
            'side' => OrderSideEnum::BUY,
            'status' => OrderStatusEnum::OPEN,
            'price' => '50000.00000000',
            'created_at' => now()->subHour(),
        ]);

        $orderbook = $this->orderService->getOrderbook(SymbolEnum::BTC);

        $this->assertEquals($oldOrder->id, $orderbook['buy_orders']->first()->id);
    }
}
