<script setup lang="ts">
import {ref, inject} from 'vue'
import axios from 'axios'
import {useOrderStore} from '@/stores/orderStore'
import type {Order} from '@/types/order'

const orderStore = useOrderStore()

const toast = inject<any>('toast')

const loading = ref(false)
const error = ref('')
const success = ref('')

const form = ref({
  symbol: 'BTC',
  side: 'buy' as 'buy' | 'sell',
  price: '',
  amount: ''
})

async function handleSubmit() {
  loading.value = true
  error.value = ''
  success.value = ''

  try {
    const response = await axios.post<{ data: { order: Order } }>('/orders', form.value)

    const newOrder: Order = response.data.data.order

    orderStore.addOrder(newOrder)

    toast?.success('Order placed successfully!')

    form.value.price = ''
    form.value.amount = ''

    setTimeout(() => success.value = '', 3000)

  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to place order'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
    <h3 class="text-xl font-bold mb-4">Place Order</h3>

    <form @submit.prevent="handleSubmit" class="space-y-4">
      <div>
        <label class="block text-sm font-medium mb-2">Symbol</label>
        <select
            v-model="form.symbol"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-blue-500"
        >
          <option value="BTC">BTC</option>
          <option value="ETH">ETH</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium mb-2">Side</label>
        <select
            v-model="form.side"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-blue-500"
        >
          <option value="buy">Buy</option>
          <option value="sell">Sell</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium mb-2">Price (USD)</label>
        <input
            v-model.number="form.price"
            type="number"
            step="0.01"
            required
            placeholder="95000.00"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-blue-500"
        />
      </div>

      <div>
        <label class="block text-sm font-medium mb-2">Amount</label>
        <input
            v-model.number="form.amount"
            type="number"
            step="0.00000001"
            required
            placeholder="0.5"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-blue-500"
        />
      </div>

      <button
          type="submit"
          :disabled="loading"
          class="w-full py-3 rounded font-semibold transition"
          :class="form.side === 'buy'
            ? 'bg-green-600 hover:bg-green-700 disabled:bg-gray-600'
            : 'bg-red-600 hover:bg-red-700 disabled:bg-gray-600'"
      >
        {{ loading ? 'Placing...' : `Place ${form.side.toUpperCase()} Order` }}
      </button>
    </form>
  </div>
</template>