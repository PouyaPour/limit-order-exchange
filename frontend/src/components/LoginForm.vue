<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '../stores/authStore'

const emit = defineEmits(['login-success'])
const authStore = useAuthStore()

const isRegister = ref(false)
const loading = ref(false)
const error = ref('')

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

async function handleSubmit() {
  loading.value = true
  error.value = ''

  try {
    const data = isRegister.value
        ? await authStore.register(form.value)
        : await authStore.login({ email: form.value.email, password: form.value.password })

    emit('login-success', data.data.user, data.data.token)
  } catch (err) {
    error.value = err.response?.data?.message || 'An error occurred'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-md mx-auto mt-20">
    <div class="bg-gray-800 rounded-lg p-8 border border-gray-700">
      <h2 class="text-2xl font-bold mb-6 text-center">
        {{ isRegister ? 'Register' : 'Login' }}
      </h2>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div v-if="isRegister">
          <label class="block text-sm font-medium mb-2">Name</label>
          <input v-model="form.name" type="text" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-blue-500" />
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">Email</label>
          <input v-model="form.email" type="email" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-blue-500" />
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">Password</label>
          <input v-model="form.password" type="password" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-blue-500" />
        </div>

        <div v-if="isRegister">
          <label class="block text-sm font-medium mb-2">Confirm Password</label>
          <input v-model="form.password_confirmation" type="password" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-blue-500" />
        </div>

        <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>

        <button type="submit" :disabled="loading" class="w-full py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 rounded font-semibold transition">
          {{ loading ? 'Processing...' : (isRegister ? 'Register' : 'Login') }}
        </button>
      </form>

      <div class="mt-4 text-center text-sm">
        <button @click="isRegister = !isRegister" class="text-blue-400 hover:text-blue-300">
          {{ isRegister ? 'Already have an account? Login' : "Don't have an account? Register" }}
        </button>
      </div>
    </div>
  </div>
</template>