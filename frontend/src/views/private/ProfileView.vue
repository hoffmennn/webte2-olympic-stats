<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const authStore = useAuthStore()

// STATE
const user    = ref(null)
const history = ref([])
const loading = ref(true)
const error   = ref(null)


const firstName     = ref('')
const lastName      = ref('')
const profileErrors = ref([])
const profileSuccess = ref(null)
const profileLoading = ref(false)

// Formulár pre zmenu hesla
const currentPassword = ref('')
const newPassword     = ref('')
const repeatPassword  = ref('')
const passwordErrors  = ref([])
const passwordSuccess = ref(null)
const passwordLoading = ref(false)

// -------------------------
// NAČÍTANIE DÁT
// -------------------------
async function fetchProfile() {
  try {
    loading.value = true

    // Paralelné načítanie profilu aj histórie naraz
    // Promise.all počká kým oba requesty dobehknú
    const [profileRes, historyRes] = await Promise.all([
      api.get('/user/profile.php'),
      api.get('/user/history.php')
    ])

    user.value    = profileRes.data.user
    history.value = historyRes.data.history

    // Predvyplnenie formulára hodnotami z DB
    firstName.value = user.value.first_name
    lastName.value  = user.value.last_name

  } catch (e) {
    error.value = 'Nepodarilo sa načítať profil'
  } finally {
    loading.value = false
  }
}

// -------------------------
// ZMENA PROFILU
// -------------------------
function validateProfile() {
  profileErrors.value = []

  if (!firstName.value) {
    profileErrors.value.push('Meno je povinné')
  } else if (firstName.value.length > 50) {
    profileErrors.value.push('Meno môže mať max. 50 znakov')
  }

  if (!lastName.value) {
    profileErrors.value.push('Priezvisko je povinné')
  } else if (lastName.value.length > 50) {
    profileErrors.value.push('Priezvisko môže mať max. 50 znakov')
  }

  return profileErrors.value.length === 0
}

async function onProfileSubmit() {
  if (!validateProfile()) return

  try {
    profileLoading.value = true
    profileSuccess.value = null

    await api.put('/user/profile.php', {
      first_name: firstName.value,
      last_name:  lastName.value
    })

    // Aktualizuj lokálny user objekt
    user.value.first_name = firstName.value
    user.value.last_name  = lastName.value
    authStore.user.first_name = firstName.value
    authStore.user.last_name  = lastName.value

    profileSuccess.value  = 'Profil bol úspešne aktualizovaný'

  } catch (e) {
    if (e.response?.data?.errors) {
      profileErrors.value = e.response.data.errors
    } else {
      profileErrors.value = ['Nastala neočakávaná chyba']
    }
  } finally {
    profileLoading.value = false
  }
}

// -------------------------
// ZMENA HESLA
// -------------------------
function validatePassword() {
  passwordErrors.value = []

  if (!currentPassword.value) {
    passwordErrors.value.push('Aktuálne heslo je povinné')
  }

  if (!newPassword.value) {
    passwordErrors.value.push('Nové heslo je povinné')
  } else if (newPassword.value.length < 8) {
    passwordErrors.value.push('Nové heslo musí mať aspoň 8 znakov')
  }

  if (newPassword.value !== repeatPassword.value) {
    passwordErrors.value.push('Heslá sa nezhodujú')
  }

  if (currentPassword.value === newPassword.value) {
    passwordErrors.value.push('Nové heslo musí byť iné ako aktuálne')
  }

  return passwordErrors.value.length === 0
}

async function onPasswordSubmit() {
  if (!validatePassword()) return

  try {
    passwordLoading.value = true
    passwordSuccess.value = null

    await api.put('/user/password.php', {
      current_password: currentPassword.value,
      new_password:     newPassword.value,
      repeat_password:  repeatPassword.value
    })

    // Vyčisti formulár po úspešnej zmene
    currentPassword.value = ''
    newPassword.value     = ''
    repeatPassword.value  = ''
    passwordSuccess.value = 'Heslo bolo úspešne zmenené'

  } catch (e) {
    if (e.response?.data?.errors) {
      passwordErrors.value = e.response.data.errors
    } else if (e.response?.data?.error) {
      passwordErrors.value = [e.response.data.error]
    } else {
      passwordErrors.value = ['Nastala neočakávaná chyba']
    }
  } finally {
    passwordLoading.value = false
  }
}

// -------------------------
// POMOCNÁ FUNKCIA
// Formátovanie dátumu pre históriu prihlásení
// -------------------------
function formatDate(dateString) {
  return new Date(dateString).toLocaleString('sk-SK')
}

function methodLabel(method) {
  return method === 'google' ? 'Google' : 'Lokálne konto'
}

onMounted(() => fetchProfile())
</script>

<template>
  <div class="profile-page">
    <h1>Môj profil</h1>

    <div v-if="loading">Načítavam...</div>
    <div v-else-if="error" class="error-box">{{ error }}</div>

    <template v-else>

      <!-- INFO O ÚČTE -->
      <section class="section">
        <h2>Informácie o účte</h2>
        <table class="info-table">
          <tr>
            <th>Meno a priezvisko</th>
            <td>{{ user.first_name }} {{ user.last_name }}</td>
          </tr>
          <tr>
            <th>E-mail</th>
            <td>{{ user.email }}</td>
          </tr>
          <tr>
            <th>Registrovaný</th>
            <td>{{ formatDate(user.created_at) }}</td>
          </tr>
        </table>
      </section>

      <!-- ZMENA MENA A PRIEZVISKA -->
      <section class="section">
        <h2>Zmena mena a priezviska</h2>

        <div v-if="profileSuccess" class="success-box">{{ profileSuccess }}</div>
        <div v-if="profileErrors.length > 0" class="error-box">
          <p v-for="err in profileErrors" :key="err">{{ err }}</p>
        </div>

        <form @submit.prevent="onProfileSubmit">
          <div class="form-group">
            <label for="firstName">Meno</label>
            <input
              id="firstName"
              v-model="firstName"
              type="text"
            />
          </div>

          <div class="form-group">
            <label for="lastName">Priezvisko</label>
            <input
              id="lastName"
              v-model="lastName"
              type="text"
            />
          </div>

          <button type="submit" :disabled="profileLoading">
            {{ profileLoading ? 'Ukladám...' : 'Uložiť zmeny' }}
          </button>
        </form>
      </section>

      <!-- ZMENA HESLA - skryje sa pre Google-only účty -->
      <section v-if="user.has_password" class="section">
        <h2>Zmena hesla</h2>

        <div v-if="passwordSuccess" class="success-box">{{ passwordSuccess }}</div>
        <div v-if="passwordErrors.length > 0" class="error-box">
          <p v-for="err in passwordErrors" :key="err">{{ err }}</p>
        </div>

        <form @submit.prevent="onPasswordSubmit">
          <div class="form-group">
            <label for="currentPassword">Aktuálne heslo</label>
            <input
              id="currentPassword"
              v-model="currentPassword"
              type="password"
            />
          </div>

          <div class="form-group">
            <label for="newPassword">Nové heslo</label>
            <input
              id="newPassword"
              v-model="newPassword"
              type="password"
            />
            <small>Minimálne 8 znakov</small>
          </div>

          <div class="form-group">
            <label for="repeatPassword">Nové heslo znova</label>
            <input
              id="repeatPassword"
              v-model="repeatPassword"
              type="password"
            />
          </div>

          <button type="submit" :disabled="passwordLoading">
            {{ passwordLoading ? 'Mením heslo...' : 'Zmeniť heslo' }}
          </button>
        </form>
      </section>

      <!-- HISTÓRIA PRIHLÁSENÍ -->
      <section class="section">
        <h2>História prihlásení</h2>

        <p v-if="!history?.length">Žiadna história prihlásení</p>

        <table v-else border="1">
          <thead>
          <tr>
            <th>Dátum a čas</th>
            <th>Spôsob prihlásenia</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="entry in history" :key="entry.id">
            <td>{{ formatDate(entry.created_at) }}</td>
            <td>{{ methodLabel(entry.method) }}</td>
          </tr>
          </tbody>
        </table>
      </section>

    </template>
  </div>
</template>

<style scoped>
/* -------------------------
   PAGE
------------------------- */
.profile-page {
    max-width: 750px;
    margin: 0 auto;
}

.profile-page h1 {
    font-family: var(--font-headings);
    color: var(--color-black);
    margin-bottom: 2rem;
}

/* -------------------------
   SEKCIE
------------------------- */
.section {
    background: var(--color-white);
    box-shadow: var(--shadow-sm);
    padding: 1.75rem;
    margin-bottom: 1rem;
}

.section h2 {
    font-family: var(--font-headings);
    font-size: 1.1rem;
    color: var(--blue-primary);
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--color-gray-100);
}

/* -------------------------
   INFO TABUĽKA
------------------------- */
.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table th,
.info-table td {
    padding: 0.65rem 0.5rem;
    font-size: 0.95rem;
    border-bottom: 1px solid var(--color-gray-100);
}

.info-table th {
    color: var(--color-gray-600);
    font-weight: 600;
    width: 40%;
    text-align: left;
}

.info-table td {
    color: var(--color-gray-800);
}

.info-table tr:last-child th,
.info-table tr:last-child td {
    border-bottom: none;
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
    padding: 0.6rem 0.85rem;
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

.form-group small {
    font-size: 0.8rem;
    color: var(--color-gray-600);
}

/* -------------------------
   TLAČIDLÁ
------------------------- */
button[type="submit"] {
    background-color: var(--blue-primary);
    color: var(--color-white);
    border: none;
    padding: 0.65rem 1.5rem;
    border-radius: var(--radius);
    font-family: var(--font-body);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
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
   HISTÓRIA TABUĽKA
------------------------- */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

thead {
    background-color: var(--blue-primary);
}

thead th {
    padding: 0.75rem 1rem;
    color: var(--color-white);
    font-weight: 600;
    text-align: left;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

tbody tr {
    border-bottom: 1px solid var(--color-gray-100);
    transition: background-color 0.15s;
}

tbody tr:nth-child(even) {
    background-color: var(--color-gray-50);
}

tbody tr:hover {
    background-color: #eef4ff;
}

tbody tr:last-child {
    border-bottom: none;
}

tbody td {
    padding: 0.75rem 1rem;
    color: var(--color-gray-800);
}

/* -------------------------
   SPRÁVY
------------------------- */
.success-box {
    background: #eafaf1;
    border: 1px solid #2ecc71;
    border-left: 4px solid #2ecc71;
    border-radius: var(--radius);
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    color: #1e8449;
    font-size: 0.9rem;
}

.error-box {
    background: #fdecea;
    border: 1px solid #e74c3c;
    border-left: 4px solid #e74c3c;
    border-radius: var(--radius);
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    color: #c0392b;
    font-size: 0.9rem;
}

.error-box p {
    margin: 0.2rem 0;
}

/* -------------------------
   RESPONZIVITA
------------------------- */
@media (max-width: 600px) {
    .section {
        padding: 1.25rem 1rem;
    }

    .info-table th {
        width: 45%;
        font-size: 0.85rem;
    }

    .info-table td {
        font-size: 0.85rem;
    }

    button[type="submit"] {
        width: 100%;
    }

    thead th,
    tbody td {
        padding: 0.65rem 0.75rem;
        font-size: 0.85rem;
    }
}
</style>
