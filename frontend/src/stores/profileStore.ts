import { defineStore } from 'pinia'
import api from '@/lib/api'

export const useProfileStore = defineStore('profile', {
    state: () => ({
        profile: null as any,
        loading: true
    }),

    actions: {
        async fetchProfile() {
            this.loading = true
            try {
                const { data } = await api.get('/profile')
                this.profile = data.data
            } catch (error) {
                console.error('Failed to fetch profile:', error)
            } finally {
                this.loading = false
            }
        },

        updateFromMatch(event: any) {
            if (!this.profile) return

            if (event.usd_change !== undefined) {
                this.profile.balance.usd = (
                    parseFloat(this.profile.balance.usd) + parseFloat(event.usd_change)
                ).toFixed(2)
            }

            if (event.symbol && event.asset_change !== undefined) {
                const asset = this.profile.assets.find((a: any) => a.symbol === event.symbol)
                const change = parseFloat(event.asset_change)

                if (asset) {
                    asset.amount = (parseFloat(asset.amount) + change).toFixed(8)
                    asset.total_amount = asset.amount
                } else if (change > 0) {
                    this.profile.assets.push({
                        symbol: event.symbol,
                        amount: change.toFixed(8),
                        locked_amount: '0.00000000',
                        total_amount: change.toFixed(8)
                    })
                }
            }
        }
    }
})