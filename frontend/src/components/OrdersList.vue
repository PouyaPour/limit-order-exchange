<script setup lang="ts">
import { computed, inject, onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'
import { useOrderStore } from '@/stores/orderStore'
import { useProfileStore } from '@/stores/profileStore'

const orderStore = useOrderStore()
const profileStore = useProfileStore()

const toast = inject<any>('toast')

const filter = ref({ symbol: '', status: '' })

const filteredOrders = computed(() => {
  return orderStore.orders.filter(order => {
    if (filter.value.symbol && order.symbol !== filter.value.symbol) return false
    if (filter.value.status && order.status !== parseInt(filter.value.status)) return false
    return true
  })
})

async function cancelOrder(orderId: number) {
  if (!confirm('Cancel this order?')) return

  try {
    await axios.post(`/orders/${orderId}/cancel`)

    orderStore.updateOrderStatus(orderId, 3)

    toast?.success('Order cancelled successfully!')

  } catch (error: any) {
    toast?.error(error.response?.data?.message || 'Failed to cancel order')
  }
}

function getStatusText(status: number) {
  return { 1: 'Open', 2: 'Filled', 3: 'Cancelled' }[status] || 'Unknown'
}

function getStatusClass(status: number) {
  return {
    1: 'text-yellow-400',
    2: 'text-green-400',
    3: 'text-gray-400'
  }[status] || ''
}

onMounted(() => {
  orderStore.fetchOrders()

  const userId = localStorage.getItem('user_id')
  if (userId) {
    window.Echo.private(`user.${userId}`)
        .listen('.order.matched', (e: any) => {
          console.log('Order matched → updating locally')

          if (e.order_id) {
            orderStore.updateOrderStatus(e.order_id, 2)
          }

          profileStore.updateFromMatch(e)

          toast?.success('Order filled successfully!')
        })
  }
})

onUnmounted(() => {
  const userId = localStorage.getItem('user_id')
  if (userId) {
    window.Echo.leave(`user.${userId}`)
  }
})
</script>

<template>
  <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
    <h3 class="text-xl font-bold mb-4">My Orders</h3>

    <div class="flex gap-2 mb-4">
      <select v-model="filter.symbol" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded text-sm">
        <option value="">All Symbols</option>
        <option value="BTC">BTC</option>
        <option value="ETH">ETH</option>
      </select>

      <select v-model="filter.status" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded text-sm">
        <option value="">All Status</option>
        <option value="1">Open</option>
        <option value="2">Filled</option>
        <option value="3">Cancelled</option>
      </select>
    </div>

    <div v-if="orderStore.loading" class="text-gray-400">Loading...</div>
    <div v-else-if="filteredOrders.length === 0" class="text-gray-500 text-center py-8">
      No orders found
    </div>
    <div v-else class="space-y-2 max-h-[600px] overflow-y-auto">
      <div v-for="order in filteredOrders" :key="order.id" class="bg-gray-700 rounded p-3">
        <div class="flex justify-between items-start mb-2">
          <div>
            <span class="font-bold">{{ order.symbol }}</span>
            <span class="ml-2 px-2 py-1 text-xs rounded"
                  :class="order.side === 'buy' ? 'bg-green-600' : 'bg-red-600'">
              {{ order.side.toUpperCase() }}
            </span>
          </div>
          <span class="text-sm" :class="getStatusClass(order.status)">
            {{ getStatusText(order.status) }}
          </span>
        </div>

        <div class="text-sm space-y-1">
          <div class="flex justify-between">
            <span class="text-gray-400">Price:</span>
            <span>${{ parseFloat(order.price).toFixed(2) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">Amount:</span>
            <span>{{ order.amount }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">Created:</span>
            <span>{{ new Date(order.created_at).toLocaleString() }}</span>
          </div>
        </div>

        <button v-if="order.status === 1"
                @click="cancelOrder(order.id)"
                class="mt-3 w-full py-2 bg-red-600 hover:bg-red-700 rounded text-sm transition">
          Cancel Order
        </button>
      </div>
    </div>
  </div>
</template>