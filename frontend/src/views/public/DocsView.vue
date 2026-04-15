<template>
  <div class="api-docs-container">
    <header class="page-header">
      <h1>API Dokumentácia</h1>
      <div class="legend">
        <p><strong>Auth:</strong> Vyžaduje sa JWT token v hlavičke (<code>Authorization: Bearer &lt;token&gt;</code>)</p>
      </div>
    </header>

    <section v-for="(module, index) in apiModules" :key="index" class="api-module">
      <h2>Modul: {{ module.name }}</h2>
      <div class="table-responsive">
        <table class="api-table">
          <thead>
          <tr>
            <th class="col-method">Metóda</th>
            <th class="col-url">URL Endpoint</th>
            <th class="col-auth">Auth</th>
            <th class="col-params">Parametre</th>
            <th class="col-response">Odpoveď (Response)</th>
            <th class="col-status">Status</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="(endpoint, eIndex) in module.endpoints" :key="eIndex">
            <td>
                <span :class="['method-badge', endpoint.method.toLowerCase()]">
                  {{ endpoint.method }}
                </span>
            </td>
            <td class="url-text">{{ endpoint.url }}</td>
            <td class="center-text">
                <span :class="['auth-badge', endpoint.auth ? 'auth-yes' : 'auth-no']">
                  {{ endpoint.auth ? 'Áno' : 'Nie' }}
                </span>
            </td>
            <td class="params-text">{{ endpoint.params }}</td>
            <td><code class="response-code">{{ endpoint.response }}</code></td>
            <td>{{ endpoint.status }}</td>
          </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const apiModules = ref([
  {
    name: 'Athletes (Športovci)',
    endpoints: [
      { method: 'GET', url: '/athletes', auth: false, params: '-', response: '{ "athletes": [...] }', status: '200' },
      { method: 'GET', url: '/athletes/{id}', auth: false, params: 'URL: id (int)', response: '{ "athlete": {...} }', status: '200, 404' },
      { method: 'POST', url: '/athletes', auth: true, params: '  {\n' +
            '    "first_name": "Meno",\n' +
            '    "last_name": "Priezvisko",\n' +
            '    "birth_country_id": {id}\n' +
            '  } ' + 'ALEBO pole objektov (bulk)', response: '{ "athlete": {...} } alebo { "inserted_ids": [...] }', status: '201, 422, 409' },


      { method: 'PUT', url: '/athletes/{id}', auth: true, params: 'URL: id (int), Body: Aktualizované dáta športovca', response: '{ "athlete": {...} }', status: '200, 422, 404' },
      { method: 'DELETE', url: '/athletes/{id}', auth: true, params: 'URL: id (int)', response: 'null', status: '204, 404' }
    ]
  },
  {
    name: 'Placements (Umiestnenia)',
    endpoints: [
      { method: 'GET', url: '/placements', auth: false, params: 'Query: filters, sort, pagination', response: '{ rows, filters, sort, pagination, dropdowns }', status: '200' },
      { method: 'GET', url: '/placements/{id}', auth: false, params: 'URL: id (int) - ID športovca', response: '{ "results": [...] }', status: '200, 404' },
      { method: 'POST', url: '/placements', auth: true, params: 'Body: athlete_id, olympic_games_id, discipline_id, placing', response: '{ "id": 1, "message": "Created" }', status: '201, 400' },
      { method: 'PUT', url: '/placements/{id}', auth: true, params: 'URL: id (int), Body: Voliteľné úpravy mapovania', response: '{ "message": "...", "id": 1 }', status: '200, 400, 404, 500' },
      { method: 'DELETE', url: '/placements/{id}', auth: true, params: 'URL: id (int)', response: 'null', status: '204, 404' }
    ]
  },
  {
    name: 'Auth (Autentifikácia)',
    endpoints: [
      { method: 'POST', url: '/auth/register', auth: false, params: 'Body: first_name, last_name, email, password, password_repeat', response: '{ "message": "...", "qr_code": "...", "secret": "..." }', status: '201, 400, 422, 409' },
      { method: 'POST', url: '/auth/login', auth: false, params: 'Body: email, password, totp (2FA)', response: '{ "token": "jwt...", "user": {...} }', status: '200, 400, 401, 422' },
      { method: 'GET', url: '/auth/google/callback', auth: false, params: 'Query: code, state, error (od Google)', response: 'Presmerovanie na FE s tokenom v URL', status: 'Redirect' }
    ]
  },
  {
    name: 'Users (Používatelia)',
    endpoints: [
      { method: 'GET', url: '/users/me', auth: true, params: '-', response: '{ "user": {...} }', status: '200, 401, 404' },
      { method: 'PUT', url: '/users/me', auth: true, params: 'Body: first_name, last_name', response: '{ "message": "Profil bol aktualizovaný" }', status: '200, 401, 422' },
      { method: 'PUT', url: '/users/me/password', auth: true, params: 'Body: current_password, new_password, repeat_password', response: '{ "message": "Heslo bolo zmenené" }', status: '200, 401, 404, 422' },
      { method: 'GET', url: '/users/me/logins', auth: true, params: '-', response: '{ "history": [...] }', status: '200, 401' }
    ]
  },
  {
    name: 'Lookups (Číselníky)',
    endpoints: [
      { method: 'GET', url: '/countries', auth: false, params: '-', response: '{ "countries": [...] }', status: '200' },
      { method: 'GET', url: '/disciplines', auth: false, params: '-', response: '{ "disciplines": [...] }', status: '200' },
      { method: 'GET', url: '/olympic_games', auth: false, params: '-', response: '{ "olympic_games": [...] }', status: '200' }
    ]
  },
  {
    name: 'Import',
    endpoints: [
      { method: 'POST', url: '/import', auth: true, params: 'Body/File: Súbor alebo JSON/CSV dáta na import', response: 'Závisí od logiky importu', status: '200, 400' },
      { method: 'DELETE', url: '/import', auth: true, params: '-', response: 'null (Vymazanie importovaných dát)', status: '204' }
    ]
  }
]);
</script>

<style scoped>
.api-docs-container {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
  color: #333;
}

.page-header {
  margin-bottom: 30px;
  border-bottom: 2px solid #eaeaea;
  padding-bottom: 15px;
}

.page-header h1 {
  font-size: 2rem;
  margin-bottom: 10px;
  color: #2c3e50;
}

.legend p {
  margin: 5px 0;
  font-size: 0.95rem;
  color: #555;
}

.legend code {
  background-color: #f4f4f4;
  padding: 2px 6px;
  border-radius: 4px;
}

.api-module {
  margin-bottom: 40px;
}

.api-module h2 {
  font-size: 1.5rem;
  color: #2c3e50;
  margin-bottom: 15px;
  padding-bottom: 5px;
  border-bottom: 1px solid #eaeaea;
}

.table-responsive {
  overflow-x: auto;
}

.api-table {
  width: 100%;
  border-collapse: collapse;
  background-color: #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  border-radius: 8px;
  overflow: hidden;
}

.api-table th, .api-table td {
  padding: 12px 15px;
  text-align: left;
  border-bottom: 1px solid #eaeaea;
  font-size: 0.95rem;
}

.api-table th {
  background-color: #f8f9fa;
  font-weight: 600;
  color: #495057;
}

.api-table tr:last-child td {
  border-bottom: none;
}

.api-table tr:hover {
  background-color: #fdfdfd;
}

/* Col Widths */
.col-method { width: 10%; }
.col-url { width: 20%; font-weight: bold; }
.col-auth { width: 8%; text-align: center; }
.col-params { width: 25%; }
.col-response { width: 27%; }
.col-status { width: 10%; }

.url-text {
  font-family: monospace;
  font-size: 1rem;
  color: #2c3e50;
}

.params-text {
  color: #555;
}

.response-code {
  background-color: #f1f5f9;
  color: #0f172a;
  padding: 4px 8px;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.85rem;
  display: inline-block;
  word-break: break-all;
}

.center-text {
  text-align: center;
}

/* Badges */
.method-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 6px;
  font-weight: bold;
  font-size: 0.8rem;
  text-transform: uppercase;
  color: #fff;
  text-align: center;
  min-width: 60px;
}

.get { background-color: #10b981; }
.post { background-color: #3b82f6; }
.put { background-color: #f59e0b; }
.delete { background-color: #ef4444; }

.auth-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
}

.auth-yes {
  background-color: #fee2e2;
  color: #b91c1c;
}

.auth-no {
  background-color: #f1f5f9;
  color: #64748b;
}
</style>