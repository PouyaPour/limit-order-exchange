import {defineStore} from 'pinia'
import {ref} from 'vue'
import api from '@/lib/api'
import type {User, AuthResponse} from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null)
    const token = ref<string | null>(localStorage.getItem('auth_token'))
    const isAuthenticated = ref<boolean>(!!token.value)

    async function login(credentials: { email: string; password: string }) {
        const {data} = await api.post<AuthResponse>('/login', credentials)
        setAuth(data.data.user, data.data.token)
        return data
    }

    async function register(payload: {
        name: string
        email: string
        password: string
    }) {
        const {data} = await api.post<AuthResponse>('/register', payload)
        setAuth(data.data.user, data.data.token)
        return data
    }

    async function logout() {
        await api.post('/logout')
        clearAuth()
    }

    async function fetchProfile() {
        const {data} = await api.get<{ data: { user: User } }>('/profile')
        user.value = data.data.user
        return data.data
    }

    function setAuth(userData: User, authToken: string) {
        user.value = userData
        token.value = authToken
        isAuthenticated.value = true
        localStorage.setItem('auth_token', authToken)
        localStorage.setItem('user_id', String(userData.id))
    }

    function clearAuth() {
        user.value = null
        token.value = null
        isAuthenticated.value = false
        localStorage.removeItem('auth_token')
        localStorage.removeItem('user_id')
    }

    function initAuth() {
        const savedToken = localStorage.getItem('auth_token')
        if (!savedToken) return

        token.value = savedToken
        isAuthenticated.value = true

        fetchProfile().catch(() => {
            clearAuth()
        })
    }

    return {
        user,
        token,
        isAuthenticated,
        login,
        register,
        logout,
        fetchProfile,
        initAuth,
        clearAuth
    }
})
