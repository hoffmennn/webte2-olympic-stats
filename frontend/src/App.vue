<script setup>
import { RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import '@/assets/base.css'
import CookieBanner from '@/components/CookieBanner.vue'

const authStore = useAuthStore()
const router    = useRouter()

function goToProfile() {
  router.push({ name: 'profile' })
}
</script>

<template>
  <div class="app-wrapper">

    <CookieBanner/>

    <nav class="navbar">
      <div class="navbar-brand">
        <RouterLink to="/" class="logo-link">
          <img src="../public/logo.png" alt="Logo" class="navbar-logo" />
          <span class="logo-text">Slovenský olympijský výbor</span>
        </RouterLink>
      </div>

      <div class="navbar-links">
        <RouterLink to="/table">Zoznam</RouterLink>

        <template v-if="!authStore.isLoggedIn">
          <RouterLink to="/login">Prihlásenie</RouterLink>
          <RouterLink to="/register" class="btn-register">Registrácia</RouterLink>
        </template>

        <template v-else>
          <RouterLink to="/import">Import</RouterLink>

          <RouterLink to="/athleteData">Správa dát</RouterLink>

          <button @click="goToProfile" class="btn-user">
            {{ authStore.user?.first_name }} {{ authStore.user?.last_name }}
          </button>

            <button @click="authStore.logout() ; $router.push('/')" id="btn-logout">
                Odhlásiť
            </button>


        </template>
      </div>
    </nav>

    <main class="main-content">
      <RouterView />
    </main>

    <footer class="footer">
      <p>2026 WEBTE2</p>
      <p>Adam Hoffmann</p>

      <button @click="$router.push('/docs')" class="btn-docs">
        Dokumentácia API
      </button>
    </footer>

  </div>
</template>

<style>

.app-wrapper {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background-color: #f5f7fa;
}

.main-content {
  flex: 1;
  width: 100%;
  margin: 0 auto;
}



.navbar {
  background-color: #ffffff;
  border-bottom: 3px solid var(--blue-primary);
  padding: 0 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 70px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  position: sticky;
  top: 0;
  z-index: 100;
}

.logo-link {
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--blue-primary);
  font-weight: 700;
  font-size: 1.1rem;
  transition: opacity 0.2s;
}

.logo-link:hover {
  opacity: 0.8;
}

.navbar-logo {
  height: 70px;
  width: auto;
  border-radius: 4px;
}

.logo-text {
  color: var(--blue-primary);
  font-size: 1.25rem;
  font-weight: 500;
}

/* Navbar links */
.navbar-links {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.navbar-links a {
  color: var(--blue-primary);
  font-weight: 500;
  font-size: 0.95rem;
  padding: 0.5rem 0.9rem;
  border-radius: 6px;
  transition: background-color 0.2s, color 0.2s;
}

.navbar-links a:hover {
  background-color: #eef2f9;
  color: var(--blue-primary-hover);
}

.navbar-links a.router-link-active {
  background-color: #dce6f5;
  color: var(--blue-primary);
  font-weight: 600;
}

.btn-register {
  background-color: var(--blue-primary);
  color: #ffffff !important;
  padding: 0.5rem 1.1rem !important;
  border-radius: 6px;
  font-weight: 600 !important;
  transition: background-color 0.2s !important;
}

.btn-register:hover {
  background-color: var(--blue-primary-hover) !important;
  color: #ffffff !important;
}

.btn-docs{
  border: 1px solid #ffffff;
  margin: 0.5rem;
  padding: 0.3rem 0.5rem;
  border-radius: 6px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s;
}

.btn-docs:hover{
  background-color: var(--blue-primary-hover);
  color: #ffffff;
}

.btn-user {
  background: none;
  border: 1px solid var(--blue-primary);
  color: var(--blue-primary);
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s;
}

.btn-user:hover {
  background-color: var(--blue-primary-hover);
  color: #ffffff;
}


#btn-logout {
  background: none;
  border: none;
  color: #c0392b;
  padding: 0.5rem 0.9rem;
  border-radius: 6px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

#btn-logout:hover {
  background-color: #fdecea;
}




.footer {
  background-color: var(--blue-primary-hover);
  border-top: 1px solid #050536;
  padding: 1.2rem 2rem;
  text-align: center;
  font-size: 0.85rem;
  color: #c0c0c0;
  justify-content: center;
  gap: 1.5rem;
}


@media (max-width: 768px) {
  .navbar {
    flex-direction: column;
    height: auto;
    padding: 1rem;
    gap: 0.75rem;
  }

  .navbar-links {
    flex-wrap: wrap;
    justify-content: center;
    width: 100%;
  }

  .main-content {
    padding: 1.5rem 1rem;
  }

  .footer {
    flex-direction: column;
    gap: 0.3rem;
  }
}
</style>
