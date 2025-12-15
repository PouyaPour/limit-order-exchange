export interface Order {
    id: number
    symbol: string
    side: 'buy' | 'sell'
    price: string
    amount: string
    status: 1 | 2 | 3
    created_at: string
}
