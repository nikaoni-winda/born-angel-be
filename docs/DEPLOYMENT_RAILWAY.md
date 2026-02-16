# Deploying Born Angel API to Railway

Step-by-step guide for deploying the Born Angel API (Laravel 12, PHP 8.2+) to Railway with MySQL.

---

## Prerequisites

- [Railway account](https://railway.app/)
- [Railway CLI](https://docs.railway.app/guides/cli) installed (`npm i -g @railway/cli`)
- GitHub repository with your code pushed
- Midtrans credentials (Server Key, Client Key, Merchant ID)
- Cloudinary URL (`cloudinary://API_KEY:API_SECRET@CLOUD_NAME`)

## 1. Create a Railway Project

**Option A — CLI:**

```bash
railway login
railway init
# Select "Empty Project", name it "born-angel-api"
railway link
```

**Option B — Dashboard:**

1. Go to [railway.app/new](https://railway.app/new)
2. Click **Deploy from GitHub repo**
3. Select your `born-angel-api` repository
4. Railway will auto-detect PHP/Laravel via Nixpacks

## 2. Add MySQL Database

1. In your Railway project dashboard, click **+ New** → **Database** → **MySQL**
2. Railway provisions the database and exposes reference variables automatically:
   - `${{MySQL.MYSQLHOST}}`
   - `${{MySQL.MYSQLPORT}}`
   - `${{MySQL.MYSQLDATABASE}}`
   - `${{MySQL.MYSQLUSER}}`
   - `${{MySQL.MYSQLPASSWORD}}`

## 3. Nixpacks Configuration

The `nixpacks.toml` file in the project root configures the build:

```toml
[phases.setup]
nixPkgs = ["php82", "php82Extensions.pdo_mysql", "php82Extensions.mbstring", "php82Extensions.xml", "php82Extensions.curl", "php82Extensions.gd"]

[phases.install]
cmds = ["composer install --optimize-autoloader --no-dev", "npm ci"]

[phases.build]
cmds = ["npm run build", "php artisan config:cache", "php artisan route:cache", "php artisan view:cache"]

[start]
cmd = "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"
```

This ensures PHP 8.2 with all required extensions, optimized Composer install, frontend build, Laravel caching, and automatic migrations on deploy.

## 4. Environment Variables

Set these on your Railway service (click your service → **Variables** tab → **Raw Editor**):

```env
APP_NAME="Born Angel"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.up.railway.app
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

MIDTRANS_SERVER_KEY=your-server-key
MIDTRANS_CLIENT_KEY=your-client-key
MIDTRANS_MERCHANT_ID=your-merchant-id
MIDTRANS_IS_PRODUCTION=true

CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME

NIXPACKS_PHP_ROOT_DIR=/app/public
NIXPACKS_PHP_FALLBACK_PATH=/index.php
```

### Generate APP_KEY

Run locally and paste the output into the `APP_KEY` variable:

```bash
php artisan key:generate --show
```

## 5. Domain Configuration

**Railway domain (automatic):**

```bash
railway domain
```

Or in the dashboard: Service → **Settings** → **Networking** → **Generate Domain**.

**Custom domain:**

1. In **Settings** → **Networking** → **Custom Domain**, enter your domain
2. Add the CNAME record Railway provides to your DNS
3. Update `APP_URL` to match

## 6. Deploy

If linked to GitHub, Railway auto-deploys on push to main. For manual deploys:

```bash
railway up
```

## 7. Scheduler Worker (Auto-Finish Bookings)

Aplikasi ini punya command `bookings:finish` yang berjalan tiap menit untuk otomatis mengubah status booking dari `confirmed` ke `finished` ketika jadwal kelas sudah lewat (`end_time < now()`). Supaya scheduler ini jalan di production, kamu perlu **service worker terpisah** di Railway.

### Cara Setup Worker di Railway

1. Di project dashboard, klik **+ New** → **Empty Service**, beri nama `scheduler`
2. Hubungkan ke repo GitHub yang sama
3. Set **environment variables** yang sama dengan service utama (terutama DB credentials)
4. Override **start command** di Settings → Deploy → **Custom Start Command**:
   ```bash
   php artisan schedule:work
   ```
   > `schedule:work` cocok untuk Railway karena berjalan di foreground (tidak butuh cron daemon).
5. Pastikan service ini **tidak punya domain** (tidak perlu menerima HTTP traffic)

### Alternatif: Single Service (Tanpa Worker Terpisah)

Jika tidak mau bikin service terpisah, ubah start command di `nixpacks.toml` supaya menjalankan scheduler di background:

```toml
[start]
cmd = "php artisan migrate --force && php artisan schedule:work & php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"
```

> **Catatan:** Cara ini lebih simpel tapi kurang reliable — kalau proses scheduler crash, tidak ada yang restart otomatis.

## 8. Midtrans — Setup Production

### Pindah dari Sandbox ke Production

1. Login ke [Midtrans Dashboard](https://dashboard.midtrans.com/)
2. Switch environment dari **Sandbox** ke **Production** (toggle di kanan atas)
3. Ambil **Server Key** dan **Client Key** production dari **Settings** → **Access Keys**
4. Update environment variables di Railway:
   ```env
   MIDTRANS_SERVER_KEY=Mid-server-xxxPRODUCTIONxxx
   MIDTRANS_CLIENT_KEY=Mid-client-xxxPRODUCTIONxxx
   MIDTRANS_IS_PRODUCTION=true
   ```

### Webhook / Notification URL

1. Di Midtrans Dashboard (mode **Production**), buka **Settings** → **Configuration**
2. Set **Payment Notification URL**:
   ```
   https://your-app.up.railway.app/api/midtrans/callback
   ```
3. Set **Finish Redirect URL**, **Unfinish Redirect URL**, dan **Error Redirect URL** sesuai kebutuhan frontend
4. Pastikan endpoint callback bisa diakses publik (tidak di-block middleware auth)

### Verifikasi Signature

Pastikan `MIDTRANS_SERVER_KEY` di Railway **sama persis** dengan yang ada di dashboard Midtrans production. Kalau beda, signature verification akan gagal dan callback tidak diproses.

### Checklist Midtrans Production

- [ ] Server Key & Client Key sudah pakai yang production (bukan sandbox)
- [ ] `MIDTRANS_IS_PRODUCTION=true` sudah di-set
- [ ] Notification URL sudah mengarah ke domain production Railway
- [ ] Test transaksi berhasil dan callback diterima (cek di `railway logs`)

## 9. Database Seeding (Optional)

To seed the database after the first deploy:

```bash
railway run php artisan db:seed
```

## 10. Post-Deploy Verification

- [ ] App loads at `https://your-app.up.railway.app`
- [ ] `POST /api/register` creates a user
- [ ] `POST /api/login` returns a Sanctum token
- [ ] `GET /api/services` returns data (confirms MySQL connection)
- [ ] Midtrans callback URL is reachable (`POST /api/midtrans/callback`)
- [ ] Cloudinary uploads work from admin endpoints
- [ ] Scheduler berjalan — booking otomatis jadi `finished` setelah jadwal lewat (cek logs service scheduler)
- [ ] Midtrans callback diterima — test transaksi dan pastikan status payment terupdate

## Troubleshooting

| Issue | Solution |
|-------|----------|
| **500 error on deploy** | Check `APP_KEY` is set. Run `railway logs` to see the actual error. |
| **Database connection refused** | Verify `DB_HOST` uses Railway reference variable `${{MySQL.MYSQLHOST}}`, not a hardcoded value. |
| **Assets not loading (404)** | Ensure `npm run build` succeeds in build logs. Check `NIXPACKS_PHP_ROOT_DIR=/app/public`. |
| **Migrations fail** | Run `railway run php artisan migrate:status` to check state. Ensure MySQL service is running. |
| **Midtrans callback 404** | Confirm route caching ran successfully. Check `railway run php artisan route:list` for the callback route. |
| **Cloudinary upload fails** | Verify `CLOUDINARY_URL` format: `cloudinary://key:secret@cloud_name`. |
| **Booking tidak otomatis finished** | Pastikan service scheduler berjalan dengan `php artisan schedule:work`. Cek logs scheduler service di Railway. |
| **Midtrans callback gagal (signature mismatch)** | Pastikan `MIDTRANS_SERVER_KEY` di Railway sama dengan yang di dashboard Midtrans production. |
| **Midtrans masih sandbox** | Set `MIDTRANS_IS_PRODUCTION=true` dan ganti Server Key/Client Key ke yang production. |
| **Build timeout** | Railway free tier has build limits. Upgrade plan or optimize `composer install` with `--no-dev`. |

### Viewing Logs

```bash
# Deploy logs
railway logs

# Build logs (check from dashboard → Deployments → click build)
```
