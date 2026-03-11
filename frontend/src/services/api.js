// src/services/api.js
import axios from 'axios'

// Jedna centrálna axios inštancia pre celú aplikáciu
// Všetky volania na backend idú cez tento objekt
const api = axios.create({
  baseURL: 'http://localhost:8080',
  headers: {
    'Content-Type': 'application/json'
  }
})

// REQUEST interceptor - spustí sa pred každým volaním
// Automaticky pridá JWT token do hlavičky ak existuje
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// RESPONSE interceptor - spustí sa po každej odpovedi
// Ak server vráti 401, token expiroval - odhlásiť používateľa
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      // Presmeruj na login - window.location kvôli tomu
      // že tu nemáme prístup k Vue Routeru
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
