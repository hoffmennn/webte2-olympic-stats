<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore.js'

const router    = useRouter()
const authStore = useAuthStore()

const email    = ref('')
const password = ref('')
const totp     = ref('')
const errors   = ref([])
const loading  = ref(false)

// -------------------------
// FRONTEND VALIDÁCIA
// -------------------------
function validate() {
  errors.value = []

  if (!email.value) {
    errors.value.push('Email je povinný')
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    errors.value.push('Email nie je v správnom formáte')
  }

  if (!password.value) {
    errors.value.push('Heslo je povinné')
  } else if (password.value.length < 8) {
    errors.value.push('Heslo musí mať aspoň 8 znakov')
  }

  if (!totp.value) {
    errors.value.push('2FA kód je povinný')
  } else if (!/^\d{6}$/.test(totp.value)) {
    errors.value.push('2FA kód musí obsahovať 6 číslic')
  }

  return errors.value.length === 0
}

// -------------------------
// ODOSLANIE FORMULÁRA
// -------------------------
async function onSubmit() {
  if (!validate()) return

  try {
    loading.value = true

    // authStore.login zavolá backend a uloží token
    await authStore.login(email.value, password.value, totp.value)

    // Po úspešnom prihlásení presmeruj na dashboard
    router.push({ name: 'home' })

  } catch (e) {
    // Backend vrátil chybu - zobrazíme ju
    const backendError = e.response?.data?.error
    if (backendError) {
      errors.value = [backendError]
    } else {
      errors.value = ['Nastala neočakávaná chyba']
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-container">
    <h1>Prihlásenie</h1>

    <!-- Chybové správy -->
    <div v-if="errors.length > 0" class="error-box">
      <p v-for="err in errors" :key="err">{{ err }}</p>
    </div>

    <form @submit.prevent="onSubmit">

      <div class="form-group">
        <label for="email">E-mail</label>
        <input
          id="email"
          v-model="email"
          type="email"
          placeholder="jan@example.sk"
          autocomplete="email"
        />
      </div>

      <div class="form-group">
        <label for="password">Heslo</label>
        <input
          id="password"
          v-model="password"
          type="password"
          autocomplete="current-password"
        />
      </div>

      <div class="form-group">
        <label for="totp">2FA kód</label>
        <input
          id="totp"
          v-model="totp"
          type="text"
          placeholder="123456"
          maxlength="6"
          autocomplete="one-time-code"
        />
        <small>6-miestny kód z Google Authenticator</small>
      </div>

      <button type="submit" :disabled="loading">
        {{ loading ? 'Prihlasujem...' : 'Prihlásiť sa' }}
      </button>

    </form>

    <p>Nemáte konto? <RouterLink to="/register">Zaregistrujte sa</RouterLink></p>
    <p>
      <a :href="`http://localhost:8080/auth/oauth2callback.php`">
        Prihlásiť cez Google
      </a>
    </p>

  </div>
</template>
<style scoped>
/* -------------------------
   CONTAINER
------------------------- */
.auth-container {
    max-width: 440px;
    margin: 3rem auto;
    background: var(--color-white);
    border-radius: var(--radius);
    box-shadow: var(--shadow-md);
    padding: 2.5rem 2rem;
}

.auth-container h1 {
    font-family: var(--font-headings);
    color: var(--color-black);
    font-size: 1.6rem;
    margin-bottom: 1.75rem;
    text-align: center;
}

/* -------------------------
   FORMULÁR
------------------------- */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 1.1rem;
}

.form-group label {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--color-gray-600);
}

.form-group input {
    padding: 0.65rem 0.85rem;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius);
    font-family: var(--font-body);
    font-size: 0.95rem;
    color: var(--color-gray-800);
    background: var(--color-gray-50);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus {
    outline: none;
    border-color: var(--blue-primary);
    background: var(--color-white);
    box-shadow: 0 0 0 3px rgba(0, 71, 186, 0.1);
}

.form-group input::placeholder {
    color: var(--color-gray-300);
}

.form-group small {
    font-size: 0.8rem;
    color: var(--color-gray-600);
}

/* -------------------------
   SUBMIT TLAČIDLO
------------------------- */
button[type="submit"] {
    width: 100%;
    background-color: var(--blue-primary);
    color: var(--color-white);
    border: none;
    padding: 0.75rem;
    border-radius: var(--radius);
    font-family: var(--font-body);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    margin-top: 0.5rem;
}

button[type="submit"]:hover:not(:disabled) {
    background-color: var(--blue-primary-hover);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

button[type="submit"]:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    transform: none;
}

/* -------------------------
   ODDELOVAČ + GOOGLE
------------------------- */
.auth-container > p {
    text-align: center;
    margin-top: 1rem;
    font-size: 0.9rem;
    color: var(--color-gray-600);
}

.auth-container > p a {
    color: var(--blue-primary);
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s;
}

.auth-container > p a:hover {
    color: var(--blue-primary-hover);
    text-decoration: underline;
}

/* Google prihlásenie - vizuálne oddelené */
.auth-container > p:last-child {
    margin-top: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid var(--color-gray-100);
}

.auth-container > p:last-child a {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--color-gray-800);
    font-weight: 600;
    border: 1px solid var(--color-gray-300);
    padding: 0.55rem 1.25rem;
    border-radius: var(--radius);
    transition: var(--transition);
}

.auth-container > p:last-child a:hover {
    border-color: var(--blue-primary);
    color: var(--blue-primary);
    text-decoration: none;
    box-shadow: var(--shadow-sm);
}

/* -------------------------
   SPRÁVY
------------------------- */
.error-box {
    background: #fdecea;
    border: 1px solid #e74c3c;
    border-left: 4px solid #e74c3c;
    border-radius: var(--radius);
    padding: 0.75rem 1rem;
    margin-bottom: 1.25rem;
    color: #c0392b;
    font-size: 0.9rem;
}

.error-box p {
    margin: 0.2rem 0;
}

/* -------------------------
   RESPONZIVITA
------------------------- */
@media (max-width: 500px) {
    .auth-container {
        margin: 1rem;
        padding: 1.75rem 1.25rem;
    }
}
</style>
