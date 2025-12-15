import { defineStore } from 'pinia'
import api from '@/lib/api'
import type { Order } from '@/types/order'

export const useOrderStore = defineStore('orders', {
    state: () => ({
        orders: [] as Order[],
        loading: true
    }),

    actions: {
        async fetchOrders() {
            this.loading = true
            try {
                const { data } = await api.get<{ data: { orders: Order[] } }>('/orders')
                this.orders = data.data.orders
            } catch (error) {
                console.error('Failed to fetch orders:', error)
            } finally {
                this.loading = false
            }
        },

        updateOrderStatus(orderId: number, newStatus: 1 | 2 | 3) {
            const order = this.orders.find(o => o.id === orderId)
            if (order) {
                order.status = newStatus
            }
        },

        addOrder(order: Order) {
            this.orders.unshift(order)
        }
    }
})