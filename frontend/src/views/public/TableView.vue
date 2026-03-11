<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import '@/assets/base.css'
const router = useRouter()


// STATE
const rows       = ref([])
const loading    = ref(true)
const error      = ref(null)
const dropdowns  = ref({ years: [], categories: [] })
const pagination = ref({ current: 1, total: 1, totalRows: 0, hasNext: false, hasPrev: false, perPage: 10 })


const selectedYear     = ref('')
const selectedCategory = ref('')


const sort = ref({ column: null, dir: null })


// NAČÍTANIE DÁT
async function fetchAthletes(page = 1) {
  try {
    loading.value = true

    // Zostavenie parametrov - posiela len tie ktoré sú nastavené
    const params = { page, per_page: pagination.value.perPage }

    if (selectedYear.value)     params.year     = selectedYear.value
    if (selectedCategory.value) params.category = selectedCategory.value
    if (sort.value.column)      params.sort      = sort.value.column
    if (sort.value.dir)         params.dir       = sort.value.dir

    const response = await api.get('api/athletes.php', { params })

    rows.value       = response.data.rows
    pagination.value = response.data.pagination
    dropdowns.value  = response.data.dropdowns

  } catch (e) {
    error.value = 'Nepodarilo sa načítať dáta'
  } finally {
    loading.value = false
  }
}


// FILTRE
function onFilterChange() {
  fetchAthletes(1)
}

function resetFilters() {
  selectedYear.value     = ''
  selectedCategory.value = ''
  sort.value             = { column: null, dir: null }
  fetchAthletes(1)
}


function onSort(column) {
  if (sort.value.column !== column) {
    sort.value = { column, dir: 'ASC' }
  } else if (sort.value.dir === 'ASC') {
    sort.value = { column, dir: 'DESC' }
  } else {
    sort.value = { column: null, dir: null }
  }
  fetchAthletes(pagination.value.current)
}

function sortIcon(column) {
  if (sort.value.column !== column) return '↕'
  return sort.value.dir === 'ASC' ? '↑' : '↓'
}

// -------------------------
// STRÁNKOVANIE
// -------------------------
function goToPage(page) {
  fetchAthletes(page)
}

function onPerPageChange() {
  fetchAthletes(1)
}

// -------------------------
// DETAIL
// -------------------------
function goToDetail(id) {
  router.push({ name: 'athlete-detail', params: { id } })
}

onMounted(() => fetchAthletes())
</script>

<template>
  <div>
    <h1>Výsledky našich olympionikov</h1>

    <div class="table-wrapper">

    <!-- FILTRE -->
    <div class="filters">
      <select v-model="selectedYear" @change="onFilterChange">
        <option value="">Všetky roky</option>
        <option v-for="year in dropdowns.years" :key="year" :value="year">
          {{ year }}
        </option>
      </select>

      <select v-model="selectedCategory" @change="onFilterChange">
        <option value="">Všetky kategórie</option>
        <option v-for="cat in dropdowns.categories" :key="cat" :value="cat">
          {{ cat }}
        </option>
      </select>

      <button @click="resetFilters">Zrušiť filtre</button>
    </div>

    <!-- POČET NA STRÁNKU -->
    <div class="per-page">
      Zobraziť:
      <select v-model="pagination.perPage" @change="onPerPageChange">
        <option :value="10">10</option>
        <option :value="20">20</option>
        <option :value="50">50</option>
        <option :value="0">Všetky</option>
      </select>
      záznamov
    </div>

    <div v-if="error" style="color:red">{{ error }}</div>

    <template v-else>
      <div class="table-container">
      <table border="1">
        <thead>
        <tr>
          <th>Umiestnenie</th>

          <th @click="onSort('surname')" style="cursor:pointer">
            Meno a priezvisko {{ sortIcon('surname') }}
          </th>

          <th v-if="!selectedYear" @click="onSort('year')" style="cursor:pointer">
            Rok {{ sortIcon('year') }}
          </th>

          <th>Krajina</th>

          <th v-if="!selectedCategory" @click="onSort('category')" style="cursor:pointer">
            Šport {{ sortIcon('category') }}
          </th>
        </tr>
        </thead>
        <tbody>
        <tr v-if="rows.length === 0">
          <td colspan="5">Žiadne záznamy</td>
        </tr>
        <tr v-for="row in rows" :key="row.id + '-' + row.year + '-' + row.discipline">
          <td>{{ row.placing }}</td>
          <td>
            <a @click="goToDetail(row.id)" >
              {{ row.first_name }} {{ row.last_name }}
            </a>
          </td>
          <td v-if="!selectedYear">{{ row.year }}</td>
          <td>{{ row.country }}</td>
          <td v-if="!selectedCategory">{{ row.discipline }}</td>
        </tr>
        </tbody>
      </table>
      </div>

      <div class="pagination">
        <button :disabled="!pagination.hasPrev" @click="goToPage(pagination.current - 1)">
          ← Predošlá
        </button>

        <span>Strana {{ pagination.current }} z {{ pagination.total }}</span>
        <span>(celkom {{ pagination.totalRows }} záznamov)</span>

        <button :disabled="!pagination.hasNext" @click="goToPage(pagination.current + 1)">
          Ďalšia →
        </button>
      </div>
    </template>

    </div>
  </div>
</template>

<style>
/* -------------------------
   NADPIS
------------------------- */
h1 {
  margin: 1.5rem;
  text-align: center;
}

/* -------------------------
   WRAPPER
------------------------- */
.table-wrapper {
  max-width: 1200px;
  margin: 1rem auto;
  background: #ffffff;
  border-radius: 10px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.07);
  padding: 1.5rem;
}

/* -------------------------
   FILTRE
------------------------- */
.filters {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
  align-items: center;
}

.filters select,
.per-page select {
  appearance: none;
  background-color: #f5f7fa;
  border: 1px solid #dde3ed;
  border-radius: 6px;
  padding: 0.5rem 2rem 0.5rem 0.85rem;
  font-size: 0.9rem;
  color: var(--blue-primary);
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%231a3a6b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.65rem center;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.filters select:hover,
.per-page select:hover {
  border-color: var(--blue-primary);
}

.filters select:focus,
.per-page select:focus {
  outline: none;
  border-color: var(--blue-primary);
  box-shadow: 0 0 0 3px rgba(26, 58, 107, 0.1);
}

/* Tlačidlo zrušiť filtre */
.filters button {
  background: none;
  border: 1px solid #c0392b;
  color: #c0392b;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.9rem;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s;
}

.filters button:hover {
  background-color: #fdecea;
}

/* -------------------------
   POČET NA STRÁNKU
------------------------- */
.per-page {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
  font-size: 0.9rem;
  color: #5a6a8a;
}

/* -------------------------
   TABUĽKA
------------------------- */
table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.92rem;
  border: none;
}

/* THEAD */
thead {
  background-color: var(--blue-primary);
}

thead th {
  padding: 0.9rem 1rem;
  color: #ffffff;
  font-weight: 600;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border: none;
  white-space: nowrap;
}

/* Klikateľné hlavičky */
thead th[style*="cursor:pointer"] {
  user-select: none;
  transition: background-color 0.15s;
}

thead th[style*="cursor:pointer"]:hover {
  background-color: #0f2550;
}

/* TBODY */
tbody tr {
  border-bottom: 1px solid #eef1f7;
  transition: background-color 0.15s;
}


tbody tr:nth-child(even) {
  background-color: #f8fafd;
}

/* Nepárne riadky */
tbody tr:nth-child(odd) {
  background-color: #ffffff;
}

tbody tr:hover {
  background-color: #eef4ff;
}

tbody tr:last-child {
  border-bottom: none;
}

td {
  padding: 0.85rem 1rem;
  color: #2c3e6b;
  vertical-align: middle;
}

tbody td a {
  color: var(--blue-primary);
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
  border-bottom: 1px solid transparent;
  transition: border-color 0.15s, color 0.15s;
  padding-bottom: 1px;
}

tbody td a:hover {
  color: #0f2550;
  border-bottom-color: var(--blue-primary);
}

/* Prázdny stav */
tbody tr td[colspan] {
  text-align: center;
  padding: 2rem;
  color: #8a9ab5;
  font-style: italic;
}

/* -------------------------
   STRÁNKOVANIE
------------------------- */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  margin-top: 1.5rem;
  flex-wrap: wrap;
}

.pagination span {
  font-size: 0.9rem;
  color: #5a6a8a;
}

.pagination button {
  background-color: #f0f4fb;
  border: 1px solid #dde3ed;
  color: var(--blue-primary);
  padding: 0.5rem 1.1rem;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s, border-color 0.2s;
}

.pagination button:hover:not(:disabled) {
  background-color: var(--blue-primary);
  color: #ffffff;
  border-color: var(--blue-primary);
}

.pagination button:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

/* -------------------------
   RESPONZIVITA
------------------------- */
@media (max-width: 768px) {
  .table-wrapper {
    padding: 1rem;
    border-radius: 0;
    box-shadow: none;
  }

  .table-container{
    overflow-x: auto;
  }

  table {
    min-width: 500px;

  }

  thead th,
  td {
    padding: 0.7rem 0.75rem;
    font-size: 0.85rem;
  }

  .filters {
    flex-direction: column;
    align-items: stretch;
  }

  .filters select,
  .filters button {
    width: 100%;
  }

  .pagination {
    gap: 0.5rem;
  }

  h1 {
    font-size: 1.4rem;
  }
}
</style>
