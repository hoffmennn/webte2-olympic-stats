<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import heroImg from '@/assets/hockey.jpg'


const router    = useRouter()
const authStore = useAuthStore()
</script>

<template>
  <div class="home">

    <section class="hero" :style="{ backgroundImage: `url(${heroImg})` }">
      <div class="hero-overlay">
        <h1>Výsledky slovenských olympionikov</h1>
        <p>Prehľad slovenských športovcov na olympijských hrách</p>
        <RouterLink to="/table" class="btn-explore">
          Zobraziť výsledky olympionikov
        </RouterLink>
      </div>
    </section>

    <section class="welcome">

      <template v-if="authStore.isLoggedIn">
        <h2>Vitajte, {{ authStore.user?.first_name }} {{ authStore.user?.last_name }}</h2>

          <p>Máte prístup k privátnej zóne portálu</p>

        <div class="action-buttons">
          <button @click="router.push({ name: 'import' })" class="btn-primary">
             Správa dát
          </button>
          <button @click="router.push({ name: 'profile' })" class="btn-secondary">
             Upraviť profil
          </button>
        </div>
      </template>

      <template v-else>
        <h2>Prihláste sa</h2>
        <p>Pre prístup k správe dát sa prosím prihláste alebo zaregistrujte.</p>

        <div class="action-buttons">
          <button @click="router.push({ name: 'login' })" class="btn-primary">
             Prihlásiť sa
          </button>
          <button @click="router.push({ name: 'register' })" class="btn-secondary">
             Registrovať sa
          </button>
        </div>
      </template>

    </section>

  </div>
</template>

<style scoped>

.hero {
  width: 100%;
  min-height: 520px;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

/* Tmavý overlay cez celý obrázok pre lepšiu čitateľnosť textu */
.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
}

.hero-overlay {
  position: relative; /* Aby bol nad ::before overlay */
  z-index: 1;
  text-align: center;
  padding: 2rem 1.5rem;
  max-width: 700px;
  width: 100%;
}

.hero-overlay h1 {
  color: var(--color-white);
  font-family: var(--font-headings);
  font-size: 3rem;
  margin-bottom: 1rem;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
}

.hero-overlay p {
  color: rgba(255, 255, 255, 0.9);
  font-size: 1.15rem;
  margin-bottom: 2rem;
}

.btn-explore {
  display: inline-block;
  padding: 0.8rem 2rem;
  background: #e74c3c;
  color: var(--color-white);
  font-family: var(--font-body);
  font-weight: 600;
  font-size: 1rem;
  border-radius: 50px; /* Oblé tlačidlo */
  text-decoration: none;
  transition: var(--transition);
  box-shadow: var(--shadow-md);
}

.btn-explore:hover {
  background: #c0392b;
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(231, 76, 60, 0.4);
  color: var(--color-white);
}

/* -------------------------
   WELCOME SEKCIA
------------------------- */
.welcome {
  max-width: 600px;
  margin: 3rem auto;
  text-align: center;
  padding: 2.5rem 2rem;
  background: var(--color-white);
  border-radius: var(--radius);
  box-shadow: var(--shadow-md);
}

.welcome h2 {
  font-family: var(--font-headings);
  color: var(--color-black);
  margin-bottom: 0.5rem;
}

.welcome p {
  color: var(--color-gray-600);
  margin-bottom: 1.5rem;
}

.action-buttons {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

.action-buttons .btn-primary {
  padding: 0.75rem 1.8rem;
  background: var(--blue-primary);
  color: var(--color-white);
  border: none;
  border-radius: 50px;
  font-family: var(--font-body);
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: var(--transition);
  box-shadow: var(--shadow-sm);
}

.action-buttons .btn-primary:hover {
  background: var(--blue-primary-hover);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.action-buttons .btn-secondary {
  padding: 0.75rem 1.8rem;
  background: transparent;
  color: var(--blue-primary);
  border: 2px solid var(--blue-primary);
  border-radius: 50px;
  font-family: var(--font-body);
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: var(--transition);
}

.action-buttons .btn-secondary:hover {
  background: var(--blue-primary);
  color: var(--color-white);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

/* -------------------------
   RESPONZIVITA
------------------------- */
@media (max-width: 768px) {
  .hero {
    min-height: 380px;
  }

  .hero-overlay h1 {
    font-size: 2rem;
  }

  .hero-overlay p {
    font-size: 1rem;
  }

  .welcome {
    margin: 2rem 1rem;
    padding: 1.5rem 1rem;
  }

  .action-buttons {
    flex-direction: column;
    align-items: center;
  }

  .action-buttons .btn-primary,
  .action-buttons .btn-secondary {
    width: 100%;
    max-width: 280px;
  }
}

@media (max-width: 480px) {
  .hero-overlay h1 {
    font-size: 1.6rem;
  }

  .btn-explore {
    padding: 0.7rem 1.5rem;
    font-size: 0.95rem;
  }
}
</style>
