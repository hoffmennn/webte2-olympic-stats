<script setup>
import { ref } from 'vue'

const visible = ref(!localStorage.getItem('cookie_consent'))

function accept() {
  localStorage.setItem('cookie_consent', '1')
  visible.value = false
}
</script>

<template>
  <div v-if="visible" class="cookie-banner">
    <p>
      Táto stránka ukladá cookies a osobné údaje (prihlasovacie údaje, história prihlásení)
      nevyhnutné pre fungovanie služby. Používaním stránky súhlasíte s ich ukladaním.
    </p>
    <button @click="accept">Súhlasím</button>
  </div>
</template>

<style scoped>
.cookie-banner {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: var(--color-white);
  border-top: 3px solid var(--blue-primary);
  box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.1);
  padding: 1rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  z-index: 999;
}

.cookie-banner p {
  font-size: 0.9rem;
  color: var(--color-gray-600);
  margin: 0;
  line-height: 1.5;
}

button {
  flex-shrink: 0;
  background-color: var(--blue-primary);
  color: var(--color-white);
  border: none;
  padding: 0.6rem 1.5rem;
  border-radius: var(--radius);
  font-family: var(--font-body);
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
}

button:hover {
  background-color: var(--blue-primary-hover);
}

@media (max-width: 600px) {
  .cookie-banner {
    flex-direction: column;
    text-align: center;
    padding: 1.25rem 1rem;
  }

  button {
    width: 100%;
  }
}
</style>
