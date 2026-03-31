import axios from 'axios'



const api = axios.create({
  //baseURL: 'https://node41.webte.fei.stuba.sk/olympic/'
    baseURL: 'http://localhost:8080/',
    headers: {
        'Content-Type': 'application/json'
    }
})


//JWT token do hlavičky ak existuje
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


//  401  - token expiroval - odhlásiť používateľa
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
