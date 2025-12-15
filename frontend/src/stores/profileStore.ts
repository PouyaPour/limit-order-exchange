import { defineStore } from 'pinia'
import api from '@/lib/api'

interface Balance {
    usd: string
}

interface Asset {
    symbol: string
    amount: string
    locked_amount: string
    total_amount: string
}

interface Profile {
    balance: Balance
    assets: Asset[]
}

export const useProfileStore = defineStore('profile', {
    state: () => ({
        profile: null as Profile | null,
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

        setBalances(balances: any) {
            if (!this.profile) {
                console.warn('⚠️ Profile not loaded yet')
                return
            }


            if (balances.usd !== undefined) {
                this.profile.balance.usd = balances.usd.toString()
            }

            if (balances.assets) {
                Object.entries(balances.assets).forEach(([symbol, data]: [string, any]) => {
                    const asset = this.profile!.assets.find(a => a.symbol === symbol)

                    if (asset) {
                        asset.amount = data.amount?.toString() || asset.amount
                        asset.locked_amount = data.locked_amount?.toString() || asset.locked_amount
                        asset.total_amount = data.total_amount?.toString() || asset.total_amount
                    } else {
                        this.profile!.assets.push({
                            symbol,
                            amount: data.amount?.toString() || '0',
                            locked_amount: data.locked_amount?.toString() || '0',
                            total_amount: data.total_amount?.toString() || '0'
                        })
                    }
                })
            }
        },

        updateFromMatch(event: any) {
            if (!this.profile) {
                console.warn('⚠️ Profile not loaded yet')
                return
            }


            if (event.balances) {
                this.setBalances(event.balances)
                return
            }

            if (event.usd_change !== undefined) {
                const currentUsd = parseFloat(this.profile.balance.usd)
                const change = parseFloat(event.usd_change)
                this.profile.balance.usd = (currentUsd + change).toFixed(2)
            }

            if (event.symbol && event.asset_change !== undefined) {
                const asset = this.profile.assets.find((a: Asset) => a.symbol === event.symbol)
                const change = parseFloat(event.asset_change)

                if (asset) {
                    const currentAmount = parseFloat(asset.amount)
                    const newAmount = (currentAmount + change).toFixed(8)
                    asset.amount = newAmount
                    asset.total_amount = newAmount
                } else if (change > 0) {
                    const newAsset = {
                        symbol: event.symbol,
                        amount: change.toFixed(8),
                        locked_amount: '0.00000000',
                        total_amount: change.toFixed(8)
                    }
                    this.profile.assets.push(newAsset)
                }
                return
            }

            if (event.trade) {
                const trade = event.trade
                const isBuyer = trade.user_side === 'buy'

                let usdChange = 0
                let assetChange = 0

                if (isBuyer) {
                    usdChange = -(parseFloat(trade.total_value) + parseFloat(trade.commission || 0))
                    assetChange = parseFloat(trade.amount)
                } else {
                    usdChange = parseFloat(trade.total_value) - parseFloat(trade.commission || 0)
                    assetChange = -parseFloat(trade.amount)
                }

                const currentUsd = parseFloat(this.profile.balance.usd)
                this.profile.balance.usd = (currentUsd + usdChange).toFixed(2)

                const asset = this.profile.assets.find((a: Asset) => a.symbol === trade.symbol)
                if (asset) {
                    const currentAmount = parseFloat(asset.amount)
                    const newAmount = (currentAmount + assetChange).toFixed(8)
                    asset.amount = newAmount
                    asset.total_amount = newAmount
                } else if (assetChange > 0) {
                    const newAsset = {
                        symbol: trade.symbol,
                        amount: assetChange.toFixed(8),
                        locked_amount: '0.00000000',
                        total_amount: assetChange.toFixed(8)
                    }
                    this.profile.assets.push(newAsset)
                }
            }
        },

        updateUsdBalance(newBalance: number | string) {
            if (!this.profile) return
            this.profile.balance.usd = newBalance.toString()
        },

        updateAssetBalance(symbol: string, amount: number | string) {
            if (!this.profile) return

            const asset = this.profile.assets.find(a => a.symbol === symbol)
            if (asset) {
                asset.amount = amount.toString()
                asset.total_amount = amount.toString()
            } else {
                this.profile.assets.push({
                    symbol,
                    amount: amount.toString(),
                    locked_amount: '0',
                    total_amount: amount.toString()
                })
            }
        }
    },

    getters: {
        usdBalance: (state) => state.profile?.balance.usd || '0',

        getAssetBalance: (state) => (symbol: string) => {
            const asset = state.profile?.assets.find(a => a.symbol === symbol)
            return asset?.amount || '0'
        },

        totalAssets: (state) => state.profile?.assets.length || 0,

        hasProfile: (state) => state.profile !== null
    }
})