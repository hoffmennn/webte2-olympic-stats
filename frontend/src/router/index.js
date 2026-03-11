// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // PUBLIC
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/public/HomeView.vue')
    },
    {
      path: '/table',
      name: 'table',
      component: () => import('@/views/public/TableView.vue')
    },
    {
      path: '/athletes/:id',
      name: 'athlete-detail',
      component: () => import('@/views/public/AthleteDetailView.vue')
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true }
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { guestOnly: true }
    },
    {
      path: '/auth/callback',
      name: 'auth-callback',
      component: () => import('@/views/AuthCallbackView.vue')
    },

    // PRIVATE
    {
      path: '/profile',
      name: 'profile',
      component: () => import('@/views/private/ProfileView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/import',
      name: 'import',
      component: () => import('@/views/private/ImportView.vue'),
      meta: { requiresAuth: true }
    },

    // 404
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue')
    }
  ]
})

// ROUTE GUARD
router.beforeEach((to) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isLoggedIn) {
    return { name: 'login' }
  }

})

export default router
