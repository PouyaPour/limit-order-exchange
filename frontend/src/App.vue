<script setup lang="ts">
import { ref, onMounted, provide, markRaw } from 'vue'
import { useAuthStore } from './stores/authStore'
import LoginForm from './components/LoginForm.vue'
import OrderForm from './components/OrderForm.vue'
import ProfileView from './components/ProfileView.vue'
import OrdersList from './components/OrdersList.vue'
import OrderbookView from './components/OrderbookView.vue'
import GlobalToast from '@/components/GlobalToast.vue'

const authStore = useAuthStore()

const toastRef = ref<InstanceType<typeof GlobalToast> | null>(null)

const toast = {
  success: (msg: string) => toastRef.value?.addToast(msg, 'success'),
  error: (msg: string) => toastRef.value?.addToast(msg, 'error'),
  info: (msg: string) => toastRef.value?.addToast(msg, 'info')
}

provide('toast', markRaw(toast))

onMounted(() => {
  authStore.initAuth()
})

function handleLoginSuccess(userData: any, token: string) {
  authStore.setAuth(userData, token)
}

async function handleLogout() {
  try {
    await authStore.logout()
  } catch (error) {
    console.error('Logout failed:', error)
  }
  window.location.reload()
}
</script>

<template>
  <div class="min-h-screen bg-gray-900 text-white">
    <header class="bg-gray-800 border-b border-gray-700">
      <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold">Exchange</h1>
        <div v-if="authStore.isAuthenticated" class="flex items-center gap-4">
          <span class="text-gray-400">{{ authStore.user?.name }}</span>
          <button
              @click="handleLogout"
              class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded transition"
          >
            Logout
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
      <div v-if="!authStore.isAuthenticated">
        <LoginForm @login-success="handleLoginSuccess" />
      </div>

      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="space-y-6">
          <OrderForm />
          <ProfileView />
        </div>
        <div>
          <OrdersList />
        </div>
        <div>
          <OrderbookView />
        </div>
      </div>
    </main>

    <GlobalToast ref="toastRef" />
  </div>
</template>