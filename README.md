# Dokumentácia nasadenia - Zadanie 1

## 1. Zmeny v konfigurácii servera

### Nginx
Pridaný `location /` blok s `try_files` direktívou pre správne fungovanie Vue Router (SPA routing):

```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

Konfiguračný súbor: `/etc/nginx/sites-enabled/node41.webte.fei.stuba.sk`



## 3. Použité frameworky a knižnice

### Backend (PHP)
| Knižnica                | Použitie |
|-------------------------|----------|
| `firebase/php-jwt`      | Generovanie a overovanie JWT tokenov |
| `robthree/twofactorauth` | Generovanie 2FA TOTP kódov |
| `bacon/bacon-qr-code`   | Generovanie QR kódov pre 2FA |
| `google/apiclient`      | Google OAuth2 prihlásenie |

### Frontend (Vue.js)
| Knižnica | Použitie |
|----------|----------|
| Vue 3 | Frontend framework |
| Vue Router | Klientske smerovanie (SPA) |
| Pinia | Správa stavu (auth store) |
| Axios | HTTP požiadavky na API |

---

## 4. Postup nasadenia

### 4.1 Príprava lokálne

**Build frontendu:**
```bash
cd frontend
npm install
npm run build
```
Vznikne priečinok `frontend/dist/` so skompilovanými súbormi.

Úprava `config.php` pre produkciu


### 4.2 Nahratie súborov na server

Pomocou programu **WinSCP**


### 4.3 Inštalácia závislostí na serveri
#### Inštalované systémové balíky

| Balík | Príkaz |
|-------|--------|
| Composer | `sudo apt install composer` |

```bash
ssh xhoffmann@147.175.105.41
cd /var/www/node41.webte.fei.stuba.sk
composer require firebase/php-jwt
composer require robthree/twofactorauth
composer require bacon/bacon-qr-code ^2
composer require google/apiclient
```

### 4.4 Vytvorenie databázy



### 4.5 Úprava Nginx konfigurácie

```bash
sudo nano /etc/nginx/sites-enabled/node41.webte.fei.stuba.sk
```

Pridať `location /` blok:
```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

Reštart Nginx:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

### 4.6 Naplnenie databázy

Po nasadení sa prihláste do aplikácie a použite stránku **Správa dát** na import CSV súboru s olympijskými dátami.

---

