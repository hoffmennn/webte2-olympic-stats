<script setup>
import { ref } from 'vue'
import api from '@/services/api'

// -------------------------
// STATE
// -------------------------
const selectedFile   = ref(null)
const importing      = ref(false)
const deleting       = ref(false)
const importResult   = ref(null)
const importError    = ref(null)
const deleteSuccess  = ref(null)
const deleteError    = ref(null)
const deleteConfirm  = ref(false)

// -------------------------
// IMPORT
// -------------------------
function onFileChange(event) {
  selectedFile.value  = event.target.files[0]
  importResult.value  = null
  importError.value   = null
}

function validateFile() {
  if (!selectedFile.value) {
    importError.value = 'Vyberte CSV súbor'
    return false
  }

  const ext = selectedFile.value.name.split('.').pop().toLowerCase()
  if (ext !== 'csv') {
    importError.value = 'Povolené sú iba CSV súbory'
    return false
  }

  return true
}

async function onImport() {
  if (!validateFile()) return

  try {
    importing.value     = true
    importResult.value  = null
    importError.value   = null

    // Súbor sa posiela ako FormData - nie JSON
    // Backend číta $_FILES['csv_file']
    const formData = new FormData()
    formData.append('csv_file', selectedFile.value)

    const response = await api.post('/import.php', formData, {
      headers: {
        // Prepiseme Content-Type - axios ho nastaví správne pre FormData
        // vrátane boundary parametra
        'Content-Type': 'multipart/form-data'
      }
    })

    importResult.value = response.data

  } catch (e) {
    importError.value = e.response?.data?.error ?? 'Nastala neočakávaná chyba'
  } finally {
    importing.value = false
  }
}

// -------------------------
// MAZANIE DÁT
// -------------------------
async function onDelete() {
  // Dvojité potvrdenie - používateľ musí kliknúť dvakrát
  if (!deleteConfirm.value) {
    deleteConfirm.value = true
    return
  }

  try {
    deleting.value      = true
    deleteSuccess.value = null
    deleteError.value   = null

    await api.delete('/import.php')

    deleteSuccess.value = 'Všetky dáta boli vymazané'
    deleteConfirm.value = false

  } catch (e) {
    deleteError.value = e.response?.data?.error ?? 'Nastala neočakávaná chyba'
    deleteConfirm.value = false
  } finally {
    deleting.value = false
  }
}

function cancelDelete() {
  deleteConfirm.value = false
}
</script>

<template>



  <div class="import-page">
    <h1>Import a správa dát</h1>


    <section class="section">
      <h2>Import CSV súboru</h2>
      <p class="hint">
        Súbor musí byť vo formáte CSV s oddeľovačom <code>;</code>
      </p>


      <div v-if="importResult" class="result-box">
        <p>Spracovaných záznamov: <strong>{{ importResult.inserted }}</strong></p>
        <p>Preskočených záznamov: <strong>{{ importResult.skipped }}</strong></p>

        <div v-if="importResult.errors?.length > 0" class="error-list">
          <p><strong>Chyby:</strong></p>
          <ul>
            <li v-for="err in importResult.errors" :key="err">{{ err }}</li>
          </ul>
        </div>
      </div>

      <div v-if="importError" class="error-box">{{ importError }}</div>

      <div class="form-group">
        <label for="csvFile">Vybrať CSV súbor</label>
        <input
          id="csvFile"
          type="file"
          accept=".csv"
          @change="onFileChange"
        />
        <small v-if="selectedFile">
          Vybraný súbor: {{ selectedFile.name }}
          ({{ (selectedFile.size / 1024).toFixed(1) }} KB)
        </small>
      </div>

      <button
        @click="onImport"
        :disabled="importing || !selectedFile"
        class="btn-primary"
      >
        {{ importing ? 'Importujem...' : 'Spustiť import' }}
      </button>
    </section>


    <section class="section danger-section">
      <h2>Vymazanie dát</h2>
      <p class="hint">
        Vymaže všetky olympijské dáta z databázy.
        Používateľské účty ostanú zachované.
        Po vymazaní je možné znovu importovať dáta.
      </p>

      <div v-if="deleteSuccess" class="success-box">{{ deleteSuccess }}</div>
      <div v-if="deleteError"   class="error-box">{{ deleteError }}</div>


      <div v-if="deleteConfirm" class="confirm-box">
        <p>Naozaj chcete vymazať všetky dáta? Táto akcia je nevratná.</p>
        <div class="confirm-buttons">
          <button
            @click="onDelete"
            :disabled="deleting"
            class="btn-danger"
          >
            {{ deleting ? 'Mažem...' : 'Áno, vymazať' }}
          </button>
          <button @click="cancelDelete" class="btn-secondary">
            Zrušiť
          </button>
        </div>
      </div>

      <button
        v-else
        @click="onDelete"
        class="btn-danger"
      >
        Vymazať všetky dáta
      </button>
    </section>

  </div>
</template>

<style scoped>
/* -------------------------
   PAGE
------------------------- */
.import-page {
    max-width: 750px;
    margin: 0 auto;
}

.import-page h1 {
    font-family: var(--font-headings);
    color: var(--color-black);
    margin-bottom: 2rem;
}

/* -------------------------
   SEKCIE
------------------------- */
.section {
    background: var(--color-white);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    padding: 1.75rem;
    margin-bottom: 1.5rem;
}

.section h2 {
    font-family: var(--font-headings);
    font-size: 1.1rem;
    color: var(--blue-primary);
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--color-gray-100);
}

.danger-section {
    border: 1px solid #f5c6cb;
}

.danger-section h2 {
    color: #c0392b;
    border-bottom-color: #fdecea;
}

/* -------------------------
   HINT TEXT
------------------------- */
.hint {
    font-size: 0.9rem;
    color: var(--color-gray-600);
    margin-bottom: 1.25rem;
    line-height: 1.5;
}

.hint code {
    background: var(--color-gray-100);
    border: 1px solid var(--color-gray-300);
    border-radius: 4px;
    padding: 0.1rem 0.4rem;
    font-size: 0.85rem;
    color: var(--blue-primary);
}

/* -------------------------
   FILE INPUT
------------------------- */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 1.25rem;
}

.form-group label {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--color-gray-600);
}

.form-group input[type="file"] {
    padding: 0.6rem 0.85rem;
    border: 1px dashed var(--color-gray-300);
    border-radius: var(--radius);
    font-family: var(--font-body);
    font-size: 0.9rem;
    color: var(--color-gray-800);
    background: var(--color-gray-50);
    cursor: pointer;
    transition: border-color 0.2s;
}

.form-group input[type="file"]:hover {
    border-color: var(--blue-primary);
}

.form-group small {
    font-size: 0.82rem;
    color: var(--color-gray-600);
}



/* -------------------------
   TLAČIDLÁ
------------------------- */
.btn-primary {
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

.btn-primary:hover:not(:disabled) {
    background-color: var(--blue-primary-hover);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-primary:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    transform: none;
}

.btn-danger {
    background-color: #e74c3c;
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

.btn-danger:hover:not(:disabled) {
    background-color: #c0392b;
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-danger:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    transform: none;
}

.btn-secondary {
    background: none;
    border: 1px solid var(--color-gray-300);
    color: var(--color-gray-600);
    padding: 0.65rem 1.5rem;
    border-radius: var(--radius);
    font-family: var(--font-body);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}

.btn-secondary:hover {
    border-color: var(--color-gray-600);
    color: var(--color-gray-800);
    background: var(--color-gray-50);
}

/* -------------------------
   VÝSLEDOK IMPORTU
------------------------- */
.result-box {
    background: #eafaf1;
    border: 1px solid #2ecc71;
    border-left: 4px solid #2ecc71;
    border-radius: var(--radius);
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
    font-size: 0.9rem;
    color: var(--color-gray-800);
}

.result-box p {
    margin-bottom: 0.3rem;
}

.result-box p:last-child {
    margin-bottom: 0;
}

.error-list {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #a9dfbf;
}

.error-list p {
    color: #e67e22;
    margin-bottom: 0.4rem;
}

.error-list ul {
    margin: 0;
    padding-left: 1.25rem;
    color: #e67e22;
    font-size: 0.85rem;
}

.error-list li {
    margin-bottom: 0.2rem;
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

/* -------------------------
   POTVRDENIE MAZANIA
------------------------- */
.confirm-box {
    background: #fff8e1;
    border: 1px solid #f0c14b;
    border-left: 4px solid #f0c14b;
    border-radius: var(--radius);
    padding: 1rem 1.25rem;
}

.confirm-box p {
    color: var(--color-gray-800);
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.confirm-buttons {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* -------------------------
   RESPONZIVITA
------------------------- */
@media (max-width: 600px) {
    .section {
        padding: 1.25rem 1rem;
    }

    .btn-primary,
    .btn-danger,
    .btn-secondary {
        width: 100%;
        text-align: center;
    }

    .confirm-buttons {
        flex-direction: column;
    }
}
</style>
