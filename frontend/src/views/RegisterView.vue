<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router    = useRouter()
const authStore = useAuthStore()

// -------------------------
// STATE
// -------------------------
const firstName      = ref('')
const lastName       = ref('')
const email          = ref('')
const password       = ref('')
const passwordRepeat = ref('')
const errors         = ref([])
const loading        = ref(false)

// Po úspešnej registrácii zobrazíme QR kód
const qrCode  = ref(null)
const secret  = ref(null)
const success = ref(false)

// -------------------------
// FRONTEND VALIDÁCIA
// -------------------------
function validate() {
  errors.value = []

  if (!firstName.value) {
    errors.value.push('Meno je povinné')
  } else if (firstName.value.length > 50) {
    errors.value.push('Meno môže mať max. 50 znakov')
  }

  if (!lastName.value) {
    errors.value.push('Priezvisko je povinné')
  } else if (lastName.value.length > 50) {
    errors.value.push('Priezvisko môže mať max. 50 znakov')
  }

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

  if (password.value !== passwordRepeat.value) {
    errors.value.push('Heslá sa nezhodujú')
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

    const data = await authStore.register(
      firstName.value,
      lastName.value,
      email.value,
      password.value,
      passwordRepeat.value
    )

    // Registrácia úspešná - zobrazíme QR kód
    // Používateľ si ho musí naskenovať pred prvým prihlásením
    qrCode.value  = data.qr_code
    secret.value  = data.secret
    success.value = true

  } catch (e) {
    // Backend validačné chyby - pole chýb
    if (e.response?.data?.errors) {
      errors.value = e.response.data.errors
    } else if (e.response?.data?.error) {
      errors.value = [e.response.data.error]
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

    <!-- STAV PO ÚSPEŠNEJ REGISTRÁCII -->
    <template v-if="success">
      <h1>Registrácia úspešná</h1>
      <p>Naskenujte QR kód v aplikácii Google Authenticator pred prvým prihlásením.</p>

      <div class="qr-container">
        <img :src="qrCode" alt="QR kód pre 2FA" />
      </div>

      <div class="secret-box">
        <p>Alebo zadajte kód manuálne:</p>
        <code>{{ secret }}</code>
      </div>

      <p>Po naskenovaní sa môžete prihlásiť:</p>
      <RouterLink to="/login">
        <button>Prejsť na prihlásenie</button>
      </RouterLink>
    </template>

    <!-- REGISTRAČNÝ FORMULÁR -->
    <template v-else>
      <h1>Registrácia</h1>

      <!-- Chybové správy z frontendu aj backendu -->
      <div v-if="errors.length > 0" class="error-box">
        <p v-for="err in errors" :key="err">{{ err }}</p>
      </div>

      <form @submit.prevent="onSubmit">

        <div class="form-group">
          <label for="firstName">Meno</label>
          <input
            id="firstName"
            v-model="firstName"
            type="text"
            placeholder="Ján"
          />
        </div>

        <div class="form-group">
          <label for="lastName">Priezvisko</label>
          <input
            id="lastName"
            v-model="lastName"
            type="text"
            placeholder="Novák"
          />
        </div>

        <div class="form-group">
          <label for="email">E-mail</label>
          <input
            id="email"
            v-model="email"
            type="email"
            placeholder="jan@example.sk"
          />
        </div>

        <div class="form-group">
          <label for="password">Heslo</label>
          <input
            id="password"
            v-model="password"
            type="password"
          />
          <small>Minimálne 8 znakov</small>
        </div>

        <div class="form-group">
          <label for="passwordRepeat">Heslo znova</label>
          <input
            id="passwordRepeat"
            v-model="passwordRepeat"
            type="password"
          />
        </div>

        <button type="submit" :disabled="loading">
          {{ loading ? 'Registrujem...' : 'Vytvoriť konto' }}
        </button>

      </form>

      <p>Už máte konto? <RouterLink to="/login">Prihláste sa</RouterLink></p>
    </template>

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
   TLAČIDLÁ
------------------------- */
button[type="submit"],
button {
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

button[type="submit"]:hover:not(:disabled),
button:hover {
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
   ODKAZ POD FORMULÁROM
------------------------- */
.auth-container > p,
.auth-container template > p {
    text-align: center;
    margin-top: 1rem;
    font-size: 0.9rem;
    color: var(--color-gray-600);
}

.auth-container a {
    color: var(--blue-primary);
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s;
}

.auth-container a:hover {
    color: var(--blue-primary-hover);
    text-decoration: underline;
}

/* -------------------------
   CHYBOVÉ SPRÁVY
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
   QR KÓD SEKCIA
------------------------- */
.auth-container > p:first-of-type {
    text-align: center;
    color: var(--color-gray-600);
    margin-bottom: 1.5rem;
}

.qr-container {
    display: flex;
    justify-content: center;
    margin: 1.5rem 0;
}

.qr-container img {
    width: 180px;
    height: 180px;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius);
    padding: 0.5rem;
    background: var(--color-white);
    box-shadow: var(--shadow-sm);
}

.secret-box {
    background: var(--color-gray-100);
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius);
    padding: 1rem;
    text-align: center;
    margin-bottom: 1.25rem;
}

.secret-box p {
    font-size: 0.85rem;
    color: var(--color-gray-600);
    margin-bottom: 0.5rem;
}

.secret-box code {
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 3px;
    color: var(--blue-primary);
    word-break: break-all;
}

/* -------------------------
   RESPONZIVITA
------------------------- */
@media (max-width: 500px) {
    .auth-container {
        margin: 1rem;
        padding: 1.75rem 1.25rem;
    }

    .qr-container img {
        width: 150px;
        height: 150px;
    }
}
</style>
