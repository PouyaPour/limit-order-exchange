<script setup lang="ts">
import { ref, onMounted, watch, onUnmounted } from 'vue'
import axios from 'axios'

const selectedSymbol = ref('BTC')
const orderbook = ref({ buy_orders: [], sell_orders: [] })
const loading = ref(true)

async function loadOrderbook() {
  loading.value = true
  try {
    const { data } = await axios.get('/orders/orderbook', {
      params: { symbol: selectedSymbol.value }
    })
    orderbook.value = data.data
  } catch (error) {
    console.error('Failed to load orderbook:', error)
  } finally {
    loading.value = false
  }
}

watch(selectedSymbol, () => {
  loadOrderbook()
  setupWebSocket()
})

function setupWebSocket() {
  window.Echo.leave(`orderbook.${selectedSymbol.value}`)

  window.Echo.channel(`orderbook.${selectedSymbol.value}`)
      .listen('.orderbook.updated', (e: any) => {
        if (e.orderbook) {
          orderbook.value = e.orderbook
        }
      })
      .listen('.order.created', (e: any) => {
        loadOrderbook()
      })
      .listen('.order.cancelled', (e: any) => {
        loadOrderbook()
      })
}

watch(selectedSymbol, () => {
  const oldSymbol = selectedSymbol.value === 'BTC' ? 'ETH' : 'BTC'
  window.Echo.leave(`orderbook.${oldSymbol}`)

  loadOrderbook()
  setupWebSocket()
})

onMounted(() => {
  loadOrderbook()
  setupWebSocket()
})

onUnmounted(() => {
  window.Echo.leave(`orderbook.${selectedSymbol.value}`)
})
</script>

<template>
  <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-bold">Orderbook</h3>
      <select
          v-model="selectedSymbol"
          class="px-3 py-2 bg-gray-700 border border-gray-600 rounded"
      >
        <option value="BTC">BTC</option>
        <option value="ETH">ETH</option>
      </select>
    </div>

    <div v-if="loading" class="text-gray-400">Loading...</div>

    <div v-else class="space-y-4">
      <div>
        <div class="text-sm font-medium text-red-400 mb-2">Sell Orders</div>
        <div v-if="orderbook.sell_orders.length === 0" class="text-gray-500 text-sm text-center py-4">
          No sell orders
        </div>
        <div v-else class="space-y-1 max-h-[250px] overflow-y-auto">
          <div
              v-for="order in orderbook.sell_orders"
              :key="order.id"
              class="bg-red-900/20 rounded p-2 text-sm flex justify-between"
          >
            <span class="font-mono">${{ parseFloat(order.price).toFixed(2) }}</span>
            <span class="text-gray-400">{{ order.amount }}</span>
          </div>
        </div>
      </div>

      <div class="border-t border-gray-700 pt-2 pb-2 text-center">
        <div class="text-xs text-gray-400">
          Spread:
          <span v-if="orderbook.sell_orders[0] && orderbook.buy_orders[0]" class="font-mono">
            ${{ (parseFloat(orderbook.sell_orders[0].price) - parseFloat(orderbook.buy_orders[0].price)).toFixed(2) }}
          </span>
          <span v-else>-</span>
        </div>
      </div>

      <div>
        <div class="text-sm font-medium text-green-400 mb-2">Buy Orders</div>
        <div v-if="orderbook.buy_orders.length === 0" class="text-gray-500 text-sm text-center py-4">
          No buy orders
        </div>
        <div v-else class="space-y-1 max-h-[250px] overflow-y-auto">
          <div
              v-for="order in orderbook.buy_orders"
              :key="order.id"
              class="bg-green-900/20 rounded p-2 text-sm flex justify-between"
          >
            <span class="font-mono">${{ parseFloat(order.price).toFixed(2) }}</span>
            <span class="text-gray-400">{{ order.amount }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>