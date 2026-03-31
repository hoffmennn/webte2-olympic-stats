import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {

  // STATE
  const token = ref(localStorage.getItem('token') || null)
  const user  = ref(JSON.parse(localStorage.getItem('user') || 'null'))

  // COMPUTED
  const isLoggedIn = computed(() => !!token.value)

  // ACTIONS


  async function login(email, password, totp) {
    const response = await api.post('/auth/login.php', {
      email,
      password,
      totp
    })

    token.value = response.data.token
    user.value  = response.data.user


    localStorage.setItem('token', token.value)
    localStorage.setItem('user', JSON.stringify(user.value))
  }

  async function register(firstName, lastName, email, password, passwordRepeat) {
    const response = await api.post('/auth/register.php', {
      first_name:      firstName,
      last_name:       lastName,
      email,
      password,
      password_repeat: passwordRepeat
    })
    // Vrátime QR kód
    return response.data
  }


  function logout() {
    token.value = null
    user.value  = null
    localStorage.removeItem('token')
    localStorage.removeItem('user')
  }

  // Zavolá sa po Google OAuth2 callback
  function loginWithToken(jwtToken) {
    token.value = jwtToken
    localStorage.setItem('token', jwtToken)

    // Dekódujeme payload z JWT - je to base64 JSON
    // Nepotrebujeme knižnicu, payload nie je šifrovaný
    const payload = JSON.parse(atob(jwtToken.split('.')[1]))
    user.value = {
      id:         payload.user_id,
      email:      payload.email,
      first_name: payload.first_name,
      last_name:  payload.last_name
    }
    localStorage.setItem('user', JSON.stringify(user.value))
  }

  return {
    token,
    user,
    isLoggedIn,
    login,
    register,
    logout,
    loginWithToken
  }
})
