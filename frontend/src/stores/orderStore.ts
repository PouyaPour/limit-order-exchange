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
            } finally {
                this.loading = false
            }
        },

        updateOrderStatus(orderId: number, newStatus: 1 | 2 | 3) {
            const order = this.orders.find(o => o.id === orderId)
            if (order) {
                order.status = newStatus
            } else {
            }
        },

        addOrder(order: Order) {
            const exists = this.orders.find(o => o.id === order.id)
            if (!exists) {
                this.orders.unshift(order)
            } else {
            }
        },

        removeOrder(orderId: number) {
            const index = this.orders.findIndex(o => o.id === orderId)
            if (index !== -1) {
                this.orders.splice(index, 1)
            }
        },

        updateOrder(orderId: number, updates: Partial<Order>) {
            const order = this.orders.find(o => o.id === orderId)
            if (order) {
                Object.assign(order, updates)
            }
        }
    },

    getters: {
        openOrders: (state) => state.orders.filter(o => o.status === 1),
        filledOrders: (state) => state.orders.filter(o => o.status === 2),
        cancelledOrders: (state) => state.orders.filter(o => o.status === 3),

        ordersBySymbol: (state) => (symbol: string) =>
            state.orders.filter(o => o.symbol === symbol)
    }
})