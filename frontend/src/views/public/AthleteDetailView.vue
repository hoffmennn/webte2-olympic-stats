<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route  = useRoute()   // prístup k URL parametrom - route.params.id
const router = useRouter()  // presmerovanie späť

const athlete = ref(null)
const results = ref([])
const loading = ref(true)
const error   = ref(null)

async function fetchAthleteDetail() {
  try {
    loading.value = true

    // ID berie z URL - /athletes/42 → route.params.id = 42
    const response = await api.get('api/detail.php', {
      params: { id: route.params.id }
    })

    athlete.value = response.data.athlete
    results.value = response.data.results

  } catch (e) {
    // 404 - športovec neexistuje
    if (e.response?.status === 404) {
      error.value = 'Športovec nebol nájdený'
    } else {
      error.value = 'Nepodarilo sa načítať dáta'
    }
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push({ name: 'table' })
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString('sk-SK')
}

onMounted(() => fetchAthleteDetail())
</script>

<template>
  <div id="athlete-detail-content">

    <button @click="goBack" id="back">Späť na zoznam</button>

    <div v-if="loading">Načítavam...</div>
    <div v-else-if="error" style="color:red">{{ error }}</div>

    <template v-else-if="athlete">


      <h1>{{ athlete.first_name }} {{ athlete.last_name }}</h1>

        <div id="athlete-info">
            <div class="info-item">
                <strong>Dátum narodenia:</strong> {{ formatDate(athlete.birth_date ?? '—') }}
            </div>
            <div class="info-item">
                <strong>Miesto narodenia:</strong> {{ athlete.birth_place ?? '—' }}
            </div>
            <div class="info-item">
                <strong>Krajina narodenia:</strong> {{ athlete.birth_country ?? '—' }}
            </div>

            <template v-if="athlete.death_date">
                <div class="info-item">
                    <strong>Dátum úmrtia:</strong> {{ athlete.death_date }}
                </div>
                <div class="info-item">
                    <strong>Miesto úmrtia:</strong>
                    {{ [athlete.death_place, athlete.death_country].filter(Boolean).join(', ') || '—' }}
                </div>
            </template>
        </div>


      <h2>Výsledky na olympijských hrách</h2>

      <p v-if="results.length === 0">Žiadne výsledky</p>

      <div v-else class="table-container">
          <table  border="1">
            <thead>
            <tr>
              <th>Rok</th>
              <th>Typ</th>
              <th>Mesto</th>
              <th>Krajina OH</th>
              <th>Disciplína</th>
              <th>Umiestnenie</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="result in results" :key="result.year + result.discipline">
              <td>{{ result.year }}</td>
              <td>{{ result.type }}</td>
              <td>{{ result.city }}</td>
              <td>{{ result.oh_country }}</td>
              <td>{{ result.discipline }}</td>
              <td>{{ result.placing ?? '—' }}</td>
            </tr>
            </tbody>
          </table>
      </div>

    </template>

  </div>
</template>

<style scoped>
#athlete-detail-content{
    max-width: 1200px;
    margin: 2rem auto;
    padding: 1rem;
}

#athlete-info{
    border-top: var(--blue-primary) 5px solid;
    border-bottom: var(--blue-primary) 5px solid;

    padding: 1rem;
    font-size: 1.15rem;
    margin-bottom: 1rem;
}

#back {
    background-color: var(--blue-primary);
    color: #ffffff;
    padding: 0.5rem 1.1rem;
    border-radius: 6px;
    font-weight: 600;
    transition: background-color 0.2s;
}

#back:hover {
    background-color: #ffffff;
    color: var(--blue-primary-hover);
    cursor: pointer;
}
</style>
