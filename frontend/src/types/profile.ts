export interface Asset {
    symbol: string
    amount: string
    locked_amount: string
    total_amount: string
}

export interface Profile {
    balance: {
        usd: string
    }
    assets: Asset[]
}
