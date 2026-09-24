import { apiClient } from './client'

export type UserRole = 'admin' | 'engineer' | 'viewer'

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
}

export async function getCsrfCookie(): Promise<void> {
  await apiClient.get('/sanctum/csrf-cookie')
}

export async function login(email: string, password: string): Promise<User> {
  await getCsrfCookie()
  const { data } = await apiClient.post<{ user: User }>('/api/login', { email, password })
  return data.user
}

export async function logout(): Promise<void> {
  await apiClient.post('/api/logout')
}

export async function getUser(): Promise<User> {
  const { data } = await apiClient.get<{ user: User }>('/api/user')
  return data.user
}
