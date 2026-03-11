<script setup>
import { onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()

onMounted(() => {
  const token = route.query.token

  if (token) {
    authStore.loginWithToken(token)
    router.push({ name: 'home' })
  } else {
    router.push({ name: 'login' })
  }
})
</script>
<template>
  <div>Prihlasujem cez Google...</div>
</template>
