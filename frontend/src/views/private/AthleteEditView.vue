<template>
  <div class="athlete-manager">
    <div class="toast-container">
      <div v-for="t in activeToasts" :key="t.id" class="toast" :class="t.type">
        {{ t.message }}
      </div>
    </div>

    <h1 class="main-title">Správa športovcov</h1>

    <div class="search-section">
      <div class="search-box">
        <label>Nájsť športovca (priezvisko):</label>
        <v-select
            v-model="selectedAthleteId"
            :options="athleteOptions"
            label="fullName"
            :reduce="athlete => athlete.id"
            @update:modelValue="onAthleteSelect"
            placeholder="Začnite písať..."
            class="custom-v-select"
        ></v-select>
      </div>


      <div class="action-buttons">
        <button class="btn btn-primary btn-add" @click="prepareCreateMode">
          + Pridať
        </button>
        <button class="btn btn-secondary btn-add" @click="prepareImportMode">
          JSON Import
        </button>
      </div>
    </div>


    <div v-if="isImporting" class="card fade-in">

      <h2 class="section-title">Hromadný import športovcov</h2>

      <div class="form-grid">
        <div class="form-group full-width">
          <label>Vyberte .json súbor s poľom športovcov</label>
          <input
              type="file"
              accept=".json,application/json"
              @change="onFileSelected"
              class="file-input"
          />
        </div>
      </div>

      <div v-if="parsedImportData" class="import-preview">
        Súbor načítaný. Nájdených záznamov na import: <strong>{{ parsedImportData.length }}</strong>
      </div>

      <div class="form-actions">
        <button
            @click="submitImport"
            class="btn btn-primary"
            :disabled="!parsedImportData"
        >
          Odoslať na server
        </button>
        <button @click="cancelImport" class="btn btn-danger">Zrušiť</button>
      </div>
    </div>


    <div v-if="athleteForm" class="card fade-in">
      <h2 class="section-title">
        {{ isEditing ? 'Upraviť profil: ' + athleteForm.last_name : 'Nový športovec' }}
      </h2>

      <form @submit.prevent="saveAthlete" class="athlete-form">
        <div class="form-grid">
          <div class="form-group">
            <label>Meno <span class="required">*</span></label>
            <input type="text" v-model="athleteForm.first_name" required placeholder="Meno" />
          </div>
          <div class="form-group">
            <label>Priezvisko <span class="required">*</span></label>
            <input type="text" v-model="athleteForm.last_name" required placeholder="Priezvisko" />
          </div>

          <div class="form-group">
            <label>Dátum narodenia</label>
            <input type="date" v-model="athleteForm.birth_date" />
          </div>
          <div class="form-group">
            <label>Miesto narodenia</label>
            <input type="text" v-model="athleteForm.birth_place" placeholder="Mesto" />
          </div>

          <div class="form-group full-width">
            <label>Krajina narodenia <span class="required">*</span></label>
            <v-select
                v-model="athleteForm.birth_country_id"
                :options="countries"
                label="name"
                :reduce="c => c.id"
                placeholder="Vyberte krajinu..."
                class="custom-v-select"
            ></v-select>
          </div>

          <div class="form-group">
            <label>Dátum úmrtia</label>
            <input type="date" v-model="athleteForm.death_date" />
          </div>
          <div class="form-group">
            <label>Miesto úmrtia</label>
            <input type="text" v-model="athleteForm.death_place" placeholder="Mesto" />
          </div>

          <div class="form-group full-width">
            <label>Krajina úmrtia</label>
            <v-select
                v-model="athleteForm.death_country_id"
                :options="countries"
                label="name"
                :reduce="c => c.id"
                placeholder="Vyberte krajinu..."
                class="custom-v-select"
            ></v-select>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Uložiť zmeny</button>
          <button v-if="isEditing" type="button" class="btn btn-danger" @click="deleteAthlete">Vymazať profil</button>
        </div>
      </form>
    </div>

    <div v-if="isEditing" class="card placements-section fade-in">
      <h2 class="section-title">Výsledky na olympijských hrách</h2>

      <div class="table-container">
        <table>
          <thead>
          <tr>
            <th>Pozícia</th>
            <th>Olympijské hry</th>
            <th>Disciplína</th>
            <th class="text-center">Akcie</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="placement in placements" :key="placement.placement_id">
            <td>
              <input
                  type="number"
                  v-model="placement.placing"
                  min="1"
                  @keypress="onlyNumbers"
                  class="table-input"
              />
            </td>
            <td>
              <v-select
                  v-model="placement.olympic_games_id"
                  :options="olympicGamesOptions"
                  label="displayLabel"
                  :reduce="og => og.id"
                  class="custom-v-select table-select"
              ></v-select>
            </td>
            <td>
              <v-select
                  v-model="placement.discipline_id"
                  :options="disciplines"
                  label="name"
                  :reduce="d => d.id"
                  class="custom-v-select table-select"
              ></v-select>
            </td>
            <td class="text-center">
              <div class="table-actions">
                <button @click="updatePlacement(placement)" class="btn-icon save" title="Uložiť">Uložiť</button>
                <button @click="deletePlacement(placement.placement_id)" class="btn-icon delete" title="Vymazať">✕</button>
              </div>
            </td>
          </tr>
          <tr v-if="placements.length === 0">
            <td colspan="4">Športovec zatiaľ nemá priradené žiadne umiestnenia.</td>
          </tr>
          </tbody>
        </table>
      </div>

      <div class="add-placement-form">
        <h3>Pridať nové umiestnenie</h3>
        <div class="placement-grid">
          <div class="form-group">
            <label>Pozícia <span class="required">*</span> </label>
            <input
                type="number"
                v-model="newPlacement.placing"
                placeholder="napr. 1"
                min="1"
                @keypress="onlyNumbers"
            />
          </div>
          <div class="form-group">
            <label>Olympijské hry <span class="required">*</span> </label>
            <v-select
                v-model="newPlacement.olympic_games_id"
                :options="olympicGamesOptions"
                label="displayLabel"
                :reduce="og => og.id"
                placeholder="Vyberte OH..."
                class="custom-v-select"
            ></v-select>
          </div>
          <div class="form-group">
            <label>Disciplína <span class="required">*</span> </label>
            <v-select
                v-model="newPlacement.discipline_id"
                :options="disciplines"
                label="name"
                :reduce="d => d.id"
                placeholder="Vyberte disciplínu..."
                class="custom-v-select"
            ></v-select>
          </div>
          <button @click="createPlacement" class="btn btn-primary btn-inline">Pridať</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/services/api'

// --- TOAST
const activeToasts = ref([])
let toastIdCounter = 0

const showToast = (message, type = 'success') => {
  const id = toastIdCounter++
  activeToasts.value.push({ id, message, type })

  setTimeout(() => {
    activeToasts.value = activeToasts.value.filter(t => t.id !== id)
  }, 3000)
}

const athletes = ref([])
const countries = ref([])
const disciplines = ref([])
const olympicGames = ref([])

const selectedAthleteId = ref(null)
const isEditing = ref(false)

const isImporting = ref(false)
const selectedFile = ref(null)
const parsedImportData = ref(null)

const athleteForm = ref(null)
const placements = ref([])

const newPlacement = ref({
  placing: null,
  olympic_games_id: null,
  discipline_id: null
})


const athleteOptions = computed(() => {
  return athletes.value.map(a => ({
    ...a,
    fullName: `${a.last_name} ${a.first_name}`
  }))
})

const olympicGamesOptions = computed(() => {
  return olympicGames.value.map(og => ({
    ...og,
    displayLabel: `${og.year} - ${og.city} (${og.type})`
  }))
})

// --- POMOCNÉ FUNKCIE ---
const onlyNumbers = (event) => {
  if (event.key < '0' || event.key > '9') {
    event.preventDefault();
  }
}

// --- INIT ---
onMounted(async () => {
  await fetchDropdownData()
})

const fetchDropdownData = async () => {
  try {
    const [athRes, countRes, discRes, ogRes] = await Promise.all([
      api.get('api/athletes'),
      api.get('api/countries'),
      api.get('api/disciplines'),
      api.get('api/olympic_games')
    ])

    athletes.value = athRes.data.athletes || athRes.data
    countries.value = countRes.data.countries || countRes.data
    disciplines.value = discRes.data.disciplines || discRes.data
    olympicGames.value = ogRes.data.olympic_games || ogRes.data
  } catch (error) {
    console.error("Chyba pri načítavaní dát do selectov:", error)
    showToast("Nepodarilo sa načítať dáta zo servera.", "error")
  }
}

// athlete
const prepareCreateMode = () => {
  isImporting.value = false
  selectedAthleteId.value = null
  isEditing.value = false
  athleteForm.value = {
    first_name: '',
    last_name: '',
    birth_date: null,
    birth_place: '',
    birth_country_id: null,
    death_date: null,
    death_place: '',
    death_country_id: null
  }
  placements.value = []
}

const onAthleteSelect = async (id) => {
  if (!id) {
    athleteForm.value = null;
    isEditing.value = false;
    isImporting.value = false
    placements.value = [];
    return;
  }
  isImporting.value = false
  isEditing.value = true;
  try {
    const [athRes, placeRes] = await Promise.all([
      api.get(`api/athletes/${id}`),
      api.get(`api/placements/${id}`)
    ])

    athleteForm.value = athRes.data.athlete || athRes.data
    // Z tvojej predchádzajúcej úpravy:
    placements.value = placeRes.data.results || placeRes.data

    newPlacement.value = { placing: null, olympic_games_id: null, discipline_id: null }
  } catch (error) {
    console.error("Chyba pri načítavaní detailov športovca:", error)
    showToast("Nepodarilo sa načítať detaily športovca.", "error")
  }
}

const prepareImportMode = () => {
  isImporting.value = true
  isEditing.value = false
  athleteForm.value = null
  selectedAthleteId.value = null
  selectedFile.value = null
  parsedImportData.value = null
}

const cancelImport = () => {
  isImporting.value = false
  selectedFile.value = null
  parsedImportData.value = null
}

const onFileSelected = (event) => {
  const file = event.target.files[0]
  if (!file) return

  // Frontend validácia typu súboru
  if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
    showToast('Prosím, nahrajte platný .json súbor.', 'error')
    event.target.value = '' // reset inputu
    return
  }

  selectedFile.value = file
  const reader = new FileReader()

  reader.onload = (e) => {
    try {
      const json = JSON.parse(e.target.result)

      // Validácia štruktúry: Musí to byť pole
      if (!Array.isArray(json)) {
        showToast('JSON súbor musí obsahovať zoznam (pole) športovcov.', 'error')
        event.target.value = ''
        parsedImportData.value = null
        return
      }

      if (json.length === 0) {
        showToast('JSON súbor je prázdny.', 'error')
        parsedImportData.value = null
        return
      }

      parsedImportData.value = json
    } catch (err) {
      showToast('Súbor neobsahuje platný formát JSON.', 'error')
      event.target.value = ''
      parsedImportData.value = null
    }
  }

  reader.readAsText(file)
}

const submitImport = async () => {
  if (!parsedImportData.value) return

  try {
    // Odosielame vyparsované pole priamo na backend
    const response = await api.post('api/athletes', parsedImportData.value)
    showToast(response.data.message || 'Import prebehol úspešne!', 'success')

    await fetchDropdownData() // Aktualizujeme selecty
    cancelImport() // Zatvoríme import kartu
  } catch (error) {
    console.error('Chyba pri hromadnom importe:', error)

    // Tvoj backend posiela { bulk_errors: [...] } pri 422
    const data = error.response?.data
    if (data?.bulk_errors && data.bulk_errors.length > 0) {
      // Zobrazíme aspoň prvú chybu na frontende
      const firstError = data.bulk_errors[0]
      showToast(`Chyba v riadku ${firstError.row}: Skontrolujte konzolu pre detaily.`, 'error')
      console.log('Chyby importu:', data.bulk_errors)
    } else {
      showToast(data?.error || 'Nastala chyba pri importe dát.', 'error')
    }
  }
}

const saveAthlete = async () => {
  if (!athleteForm.value.first_name || athleteForm.value.first_name.trim() === '') {
    showToast('Meno je povinný údaj.', 'error'); return;
  }
  if (!athleteForm.value.last_name || athleteForm.value.last_name.trim() === '') {
    showToast('Priezvisko je povinný údaj.', 'error'); return;
  }
  if (!athleteForm.value.birth_country_id) {
    showToast('Krajina narodenia je povinný údaj.', 'error'); return;
  }

  try {
    if (isEditing.value) {
      await api.put(`api/athletes/${athleteForm.value.id}`, athleteForm.value)
      showToast('Športovec bol úspešne upravený.', 'success')
    } else {
      const response = await api.post('api/athletes', [athleteForm.value])
      showToast('Športovec bol úspešne vytvorený.', 'success')

      const newAthlete = response.data.athlete || response.data
      await fetchDropdownData()
      selectedAthleteId.value = newAthlete.id
      onAthleteSelect(newAthlete.id)
    }
  } catch (error) {
    console.error("Chyba pri ukladaní športovca:", error)
    showToast("Vyskytla sa chyba pri ukladaní.", "error")
  }
}

const deleteAthlete = async () => {

  try {
    await api.delete(`api/athletes/${athleteForm.value.id}`)
    showToast('Športovec bol vymazaný.', 'success')
    athleteForm.value = null
    selectedAthleteId.value = null
    await fetchDropdownData()
  } catch (error) {
    console.error("Chyba pri mazaní:", error)
    showToast("Chyba pri mazaní športovca.", "error")
  }
}

// --- AKCIE: PLACEMENTS ---
const updatePlacement = async (placement) => {
  const placingNum = Number(placement.placing);
  if (!placement.placing || placingNum < 1 || !Number.isInteger(placingNum)) {
    showToast('Pozícia musí byť celé číslo väčšie ako 0.', 'error');
    return;
  }

  try {
    await api.put(`api/placements/${placement.placement_id}`, {
      placing: placingNum,
      olympic_games_id: placement.olympic_games_id,
      discipline_id: placement.discipline_id,
      athlete_id: athleteForm.value.id
    })
    showToast('Umiestnenie bolo úspešne upravené.', 'success')
  } catch (error) {
    console.error("Chyba pri úprave umiestnenia:", error)
    showToast("Nepodarilo sa upraviť umiestnenie.", "error")
  }
}

const deletePlacement = async (placementId) => {

  try {
    await api.delete(`api/placements/${placementId}`)
    placements.value = placements.value.filter(p => p.placement_id !== placementId)
    showToast('Umiestnenie bolo vymazané.', 'success')
  } catch (error) {
    console.error("Chyba pri mazaní umiestnenia:", error)
    showToast("Chyba pri mazaní umiestnenia.", "error")
  }
}

const createPlacement = async () => {
  const placingNum = Number(newPlacement.value.placing);

  if (!newPlacement.value.placing || placingNum < 1 || !Number.isInteger(placingNum)) {
    showToast('Pozícia musí byť celé číslo väčšie ako 0.', 'error'); return;
  }
  if (!newPlacement.value.olympic_games_id) {
    showToast('Vyberte olympijské hry.', 'error'); return;
  }
  if (!newPlacement.value.discipline_id) {
    showToast('Vyberte disciplínu.', 'error'); return;
  }

  try {
    const payload = {
      athlete_id: athleteForm.value.id,
      placing: placingNum,
      olympic_games_id: newPlacement.value.olympic_games_id,
      discipline_id: newPlacement.value.discipline_id
    }

    await api.post('api/placements', payload)
    showToast('Umiestnenie bolo úspešne pridané!', 'success')



    await refreshPlacements();
    newPlacement.value = { placing: null, olympic_games_id: null, discipline_id: null }
  } catch (error) {
    console.error("Chyba pri pridávaní umiestnenia:", error)
    showToast("Nepodarilo sa uložiť umiestnenie.", "error")
  }
}

const refreshPlacements = async () => {
  try {
    const placeRes = await api.get(`api/placements/${athleteForm.value.id}`)

    let newData = placeRes.data.results || placeRes.data;

    if (Array.isArray(newData)) {
      placements.value = newData;
    } else {
      console.error("API nevrátilo pole dát pre umiestnenia. Štruktúra:", placeRes.data);
      placements.value = []; // aspoň to zabráni zlyhaniu Vue
    }

  } catch (error) {
    console.error("Chyba pri načítaní umiestnení:", error)
  }
}
</script>

<style scoped>
/* KONŠTANTY A RESET PRE KOMPONENT */
.athlete-manager {
  --blue-primary: #0047ba;
  --blue-primary-hover: #003282;
  --text-dark: #1a1a1a;
  --text-muted: #6b7280;
  --bg-card: #ffffff;
  --border-color: #e5e7eb;

  font-family: 'Inter', -apple-system, sans-serif;
  color: var(--text-dark);
  max-width: 1100px;
  margin: 2rem auto;
  padding: 0 1rem;
  position: relative;
}

/* TOAST STYLES */
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.toast {
  padding: 12px 20px;
  border-radius: 6px;
  color: #fff;
  font-weight: 500;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  animation: slideInRight 0.3s ease-out forwards;
  min-width: 250px;
}

.toast.success {
  background-color: #10b981; /* Zelená */
}

.toast.error {
  background-color: #ef4444; /* Červená */
}

@keyframes slideInRight {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

/* OSTATNÉ ŠTÝLY (Bezo zmeny) */
h1, h2, h3, h4, h5, h6 {
  font-family: var(--font-headings);
  color: var(--color-black);
  font-weight: 800;
  line-height: 1.2;
  margin-bottom: 1rem;
}

h1 { font-size: 2.5rem; }
h2 { font-size: 2rem; }


.section-title {
  font-size: 1.3rem;
  font-weight: 600;
  margin-bottom: 1.5rem;
  color: var(--blue-primary);
}

.search-section {
  display: flex;
  align-items: flex-end;
  gap: 1.5rem;
  margin-bottom: 3rem;
  background: #f3f4f6;
  padding: 1.5rem;
  border-radius: 8px;
}

.search-box {
  flex: 1;
  min-width: 250px;
}

.search-box label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: var(--text-muted);
}

.card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.athlete-form .form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 0.4rem;
}

input[type="text"],
input[type="date"],
input[type="number"] {
  padding: 0.6rem 0.8rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.95rem;
  transition: border-color 0.2s;
}

input:focus {
  outline: none;
  border-color: var(--blue-primary);
  box-shadow: 0 0 0 3px rgba(0, 71, 186, 0.1);
}

.btn {
  padding: 0.7rem 1.5rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  font-size: 0.9rem;
}

.btn-primary {
  background-color: var(--blue-primary);
  color: white;
}

.btn-primary:hover {
  background-color: var(--blue-primary-hover);
}

.btn-danger {
  background-color: #ef4444;
  color: white;
}

.btn-danger:hover {
  background-color: #dc2626;
}

.form-actions {
  margin-top: 2rem;
  display: flex;
  gap: 1rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border-color);
}

.table-container {
  overflow-x: auto;
  margin: 1.5rem 0;
  border-radius: 8px;
  border: 1px solid var(--border-color);
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.92rem;
  border: none;
}

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
  text-align: left;
}

tbody tr {
  border-bottom: 1px solid #eef1f7;
}

tbody tr:nth-child(even) { background-color: #f8fafd; }
tbody tr:nth-child(odd) { background-color: #ffffff; }
tbody tr:hover { background-color: #eef4ff; }

td {
  padding: 0.85rem 1rem;
  color: #2c3e6b;
  vertical-align: middle;
}

.table-input {
  width: 100%;
  padding: 0.4rem;
  border: 1px solid transparent;
  background: transparent;
}

.table-input:focus {
  background: white;
  border-color: var(--blue-primary);
}

.table-select {
  min-width: 200px;
}

.table-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
}

.btn-icon {
  color: #2f2c2c;
  background: none;
  border: none;
  font-size: 1.1rem;
  cursor: pointer;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  transition: background 0.2s;
  font-weight: bold;
}
.delete{
  color:#ef4444 ;
}

.btn-icon.save:hover { background: #dcfce7; color: rgb(47, 44, 44); }
.btn-icon.delete:hover { background: #fee2e2; color: #ef4444; }

.add-placement-form {
  background: #f9fafb;
  padding: 1.5rem;
  border-radius: 8px;
  margin-top: 2rem;
}

.placement-grid {
  display: grid;
  grid-template-columns: 80px 1fr 1fr auto;
  gap: 1rem;
  align-items: flex-end;
}

.custom-v-select {
  --vs-font-size: 0.95rem;
  --vs-line-height: 1.4;
  --vs-border-color: #d1d5db;
  --vs-border-radius: 6px;
  --vs-actions-padding: 4px 10px;
}

.required {
  color: #ef4444;
  font-weight: bold;
  margin-left: 2px;
}

/* Pridať na koniec <style scoped> */
.action-buttons {
  display: flex;
  gap: 0.8rem;
}

.btn-secondary {
  background-color: #f3f4f6;
  color: var(--text-dark);
  border: 1px solid var(--border-color);
}

.btn-secondary:hover {
  background-color: #e5e7eb;
}

.file-input {
  background-color: #f9fafb;
  padding: 1rem !important;
  border: 2px dashed #d1d5db !important;
  cursor: pointer;
  width: 100%;
}

.file-input:hover {
  border-color: var(--blue-primary) !important;
}

.import-preview {
  margin-top: 1rem;
  padding: 1rem;
  background-color: #eff6ff;
  border-left: 4px solid var(--blue-primary);
  border-radius: 4px;
  color: #1e3a8a;
}

@media (max-width: 768px) {
  .search-section {
    flex-direction: column;
    align-items: stretch;
  }

  .placement-grid {
    grid-template-columns: 1fr;
  }

  .btn-inline {
    width: 100%;
  }

  .form-actions {
    flex-direction: column;
  }
}

.fade-in {
  animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>