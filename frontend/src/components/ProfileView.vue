<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useProfileStore } from '@/stores/profileStore'

const profileStore = useProfileStore()

onMounted(() => {
  profileStore.fetchProfile()

  const userId = localStorage.getItem('user_id')
  if (userId) {
    window.Echo.private(`user.${userId}`)
        .listen('.order.matched', (e: any) => {
          console.log('Order matched → updating balance & assets locally')
          profileStore.updateFromMatch(e)
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
    <h3 class="text-xl font-bold mb-4">Balance & Assets</h3>

    <div v-if="profileStore.loading" class="text-gray-400">Loading...</div>

    <div v-else-if="profileStore.profile" class="space-y-4">
      <div class="bg-gray-700 rounded p-4">
        <div class="text-sm text-gray-400">USD Balance</div>
        <div class="text-2xl font-bold">${{ parseFloat(profileStore.profile.balance.usd).toFixed(2) }}</div>
      </div>

      <div class="space-y-2">
        <div class="text-sm font-medium text-gray-400">Assets</div>
        <div v-if="profileStore.profile.assets.length === 0" class="text-gray-500 text-sm">
          No assets yet
        </div>
        <div v-for="asset in profileStore.profile.assets"
             :key="asset.symbol"
             class="bg-gray-700 rounded p-3 flex justify-between items-center">
          <div>
            <div class="font-semibold">{{ asset.symbol }}</div>
            <div class="text-xs text-gray-400">Available: {{ asset.amount }}</div>
            <div class="text-xs text-gray-400">Locked: {{ asset.locked_amount }}</div>
          </div>
          <div class="text-right">
            <div class="font-bold">{{ asset.total_amount }}</div>
            <div class="text-xs text-gray-400">Total</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>