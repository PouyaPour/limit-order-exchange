<script setup lang="ts">
import {ref} from 'vue'

interface Toast {
  id: number
  message: string
  type: 'success' | 'error' | 'info'
}

const toasts = ref<Toast[]>([])
let nextId = 0

function addToast(message: string, type: 'success' | 'error' | 'info' = 'info', duration = 4000) {
  const id = nextId++
  toasts.value.push({id, message, type})

  if (duration > 0) {
    setTimeout(() => {
      removeToast(id)
    }, duration)
  }
}

function removeToast(id: number) {
  toasts.value = toasts.value.filter(t => t.id !== id)
}

defineExpose({addToast})

</script>

<template>
  <div class="fixed top-4 right-4 z-50 space-y-3">
    <transition-group name="toast" tag="div">
      <div
          v-for="toast in toasts"
          :key="toast.id"
          class="min-w-80 max-w-sm px-4 py-3 rounded-lg shadow-lg text-white font-medium text-sm transition-all"
          :class="{
            'bg-green-600': toast.type === 'success',
            'bg-red-600': toast.type === 'error',
            'bg-blue-600': toast.type === 'info'
          }"
      >
        <div class="flex justify-between items-center">
          <span>{{ toast.message }}</span>
          <button @click="removeToast(toast.id)" class="ml-4 text-white/70 hover:text-white">
            ✕
          </button>
        </div>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.4s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>