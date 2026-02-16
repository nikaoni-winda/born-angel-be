# Panduan Deploy Born Angel API ke Railway

Panduan langkah demi langkah untuk deploy Born Angel API (Laravel 12, PHP 8.2+) ke Railway dengan database MySQL.

> **Keterangan status:**
> - ✅ **Sudah ada** — File/konfigurasi sudah tersedia di codebase, tidak perlu dibuat lagi
> - ⚙️ **Perlu dilakukan** — Langkah yang harus kamu lakukan manual di Railway/dashboard

---

## Prasyarat

| Kebutuhan | Status |
|-----------|--------|
| Akun [Railway](https://railway.app/) | ⚙️ Perlu daftar/login |
| [Railway CLI](https://docs.railway.app/guides/cli) (`npm i -g @railway/cli`) | ⚙️ Perlu install |
| Repositori GitHub dengan kode sudah di-push | ⚙️ Perlu push |
| Kredensial Midtrans (Server Key, Client Key, Merchant ID) | ⚙️ Perlu ambil dari dashboard Midtrans |
| Cloudinary URL (`cloudinary://API_KEY:API_SECRET@CLOUD_NAME`) | ⚙️ Perlu ambil dari dashboard Cloudinary |

## 1. Buat Project di Railway ⚙️

**Opsi A — Lewat CLI:**

```bash
railway login
railway init
# Pilih "Empty Project", beri nama "born-angel-api"
railway link
```

**Opsi B — Lewat Dashboard:**

1. Buka [railway.app/new](https://railway.app/new)
2. Klik **Deploy from GitHub repo**
3. Pilih repositori `born-angel-api`
4. Railway akan otomatis mendeteksi PHP/Laravel lewat Nixpacks

## 2. Tambahkan Database MySQL ⚙️

1. Di dashboard project Railway, klik **+ New** → **Database** → **MySQL**
2. Railway akan otomatis membuat database dan menyediakan variabel referensi:
   - `${{MySQL.MYSQLHOST}}`
   - `${{MySQL.MYSQLPORT}}`
   - `${{MySQL.MYSQLDATABASE}}`
   - `${{MySQL.MYSQLUSER}}`
   - `${{MySQL.MYSQLPASSWORD}}`

## 3. Konfigurasi Nixpacks ✅ Sudah Ada

> **File `nixpacks.toml` sudah ada di root project.** Tidak perlu dibuat ulang.

Isi file `nixpacks.toml` yang sudah tersedia:

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

File ini mengatur:
- PHP 8.2 dengan semua ekstensi yang dibutuhkan
- Install dependensi Composer (mode produksi) dan npm
- Build frontend, caching config/route/view Laravel
- Migrasi otomatis dan start server saat deploy

## 4. Variabel Environment ⚙️

Set variabel ini di service Railway (klik service → tab **Variables** → **Raw Editor**):

```env
APP_NAME="Born Angel"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nama-app-kamu.up.railway.app
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

MIDTRANS_SERVER_KEY=kunci-server-midtrans
MIDTRANS_CLIENT_KEY=kunci-client-midtrans
MIDTRANS_MERCHANT_ID=merchant-id-midtrans
MIDTRANS_IS_PRODUCTION=true

CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME

NIXPACKS_PHP_ROOT_DIR=/app/public
NIXPACKS_PHP_FALLBACK_PATH=/index.php
```

### Generate APP_KEY

Jalankan di lokal lalu salin hasilnya ke variabel `APP_KEY` di Railway:

```bash
php artisan key:generate --show
```

### Catatan Penting tentang Variabel

| Variabel | Keterangan |
|----------|------------|
| `DB_HOST`, `DB_PORT`, dll. | Gunakan sintaks referensi Railway `${{MySQL.xxx}}`, **jangan** hardcode |
| `MIDTRANS_IS_PRODUCTION` | Set `true` untuk mode produksi, `false` untuk sandbox |
| `NIXPACKS_PHP_ROOT_DIR` | Wajib di-set agar Railway mengarahkan request ke folder `public/` |

## 5. Konfigurasi Domain ⚙️

**Domain Railway (otomatis):**

```bash
railway domain
```

Atau lewat dashboard: Service → **Settings** → **Networking** → **Generate Domain**.

**Domain kustom (opsional):**

1. Di **Settings** → **Networking** → **Custom Domain**, masukkan domain kamu
2. Tambahkan CNAME record yang diberikan Railway ke DNS kamu
3. Perbarui `APP_URL` agar sesuai dengan domain baru

## 6. Deploy ⚙️

Kalau sudah terhubung ke GitHub, Railway otomatis deploy setiap push ke branch main. Untuk deploy manual:

```bash
railway up
```

## 7. Worker Scheduler (Auto-Finish Bookings) ⚙️

> **Kode scheduler sudah ada di codebase** ✅ — Command `bookings:finish` sudah dibuat di `app/Console/Commands/FinishCompletedBookings.php` dan sudah terdaftar di `routes/console.php` untuk berjalan tiap menit. Yang perlu kamu lakukan adalah **setup service worker di Railway** supaya scheduler ini berjalan di production.

Command ini otomatis mengubah status booking dari `confirmed` ke `finished` ketika `end_time` jadwal sudah lewat.

### Cara Setup Worker di Railway ⚙️

1. Di dashboard project, klik **+ New** → **Empty Service**, beri nama `scheduler`
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

## 8. Midtrans — Setup Production ⚙️

> **Konfigurasi Midtrans di kode sudah ada** ✅ — File `config/midtrans.php` sudah mengatur server key, client key, toggle sandbox/production, URL Snap, dan daftar metode pembayaran. Endpoint callback `POST /api/payments/callback` juga sudah ada di `routes/api.php`. Yang perlu kamu lakukan adalah **mengisi kredensial production dan setup webhook di dashboard Midtrans**.

### Pindah dari Sandbox ke Production ⚙️

1. Login ke [Midtrans Dashboard](https://dashboard.midtrans.com/)
2. Switch environment dari **Sandbox** ke **Production** (toggle di kanan atas)
3. Ambil **Server Key** dan **Client Key** production dari **Settings** → **Access Keys**
4. Update environment variables di Railway:
   ```env
   MIDTRANS_SERVER_KEY=Mid-server-xxxPRODUCTIONxxx
   MIDTRANS_CLIENT_KEY=Mid-client-xxxPRODUCTIONxxx
   MIDTRANS_IS_PRODUCTION=true
   ```

### Webhook / Notification URL ⚙️

1. Di Midtrans Dashboard (mode **Production**), buka **Settings** → **Configuration**
2. Set **Payment Notification URL**:
   ```
   https://nama-app-kamu.up.railway.app/api/payments/callback
   ```
3. Set **Finish Redirect URL**, **Unfinish Redirect URL**, dan **Error Redirect URL** sesuai kebutuhan frontend
4. Pastikan endpoint callback bisa diakses publik (tidak di-block middleware auth)

> **Catatan:** Endpoint callback (`POST /api/payments/callback`) sudah diletakkan di luar middleware `auth:sanctum` di `routes/api.php`, jadi Midtrans bisa mengaksesnya tanpa token. ✅

### Verifikasi Signature

Pastikan `MIDTRANS_SERVER_KEY` di Railway **sama persis** dengan yang ada di dashboard Midtrans production. Kalau beda, verifikasi signature akan gagal dan callback tidak diproses.

### Checklist Midtrans Production

- [ ] Server Key & Client Key sudah pakai yang production (bukan sandbox)
- [ ] `MIDTRANS_IS_PRODUCTION=true` sudah di-set di Railway
- [ ] Notification URL sudah mengarah ke domain production Railway
- [ ] Test transaksi berhasil dan callback diterima (cek di `railway logs`)

## 9. Seeding Database (Opsional) ⚙️

Untuk mengisi database dengan data awal setelah deploy pertama:

```bash
railway run php artisan db:seed
```

> **Seeder sudah ada di codebase** ✅ — File seeder di `database/seeders/` sudah tersedia dengan akun default (super admin, admin, instruktur, user).

## 10. Verifikasi Setelah Deploy ⚙️

Checklist untuk memastikan semuanya berjalan:

- [ ] Aplikasi bisa diakses di `https://nama-app-kamu.up.railway.app`
- [ ] `POST /api/register` berhasil membuat user baru
- [ ] `POST /api/login` mengembalikan token Sanctum
- [ ] `GET /api/services` mengembalikan data (konfirmasi koneksi MySQL berhasil)
- [ ] Endpoint callback Midtrans bisa dijangkau (`POST /api/payments/callback`)
- [ ] Upload gambar Cloudinary berfungsi dari endpoint admin
- [ ] Scheduler berjalan — booking otomatis jadi `finished` setelah jadwal lewat (cek logs service scheduler)
- [ ] Callback Midtrans diterima — test transaksi dan pastikan status payment terupdate

## Rangkuman Status

| Komponen | Status | Keterangan |
|----------|--------|------------|
| `nixpacks.toml` | ✅ Sudah ada | Konfigurasi build PHP 8.2 + Laravel |
| `config/midtrans.php` | ✅ Sudah ada | Konfigurasi Midtrans (key, sandbox/production, metode bayar) |
| `config/cloudinary.php` | ✅ Sudah ada | Konfigurasi Cloudinary |
| Endpoint callback Midtrans | ✅ Sudah ada | `POST /api/payments/callback` di luar auth middleware |
| Command `bookings:finish` | ✅ Sudah ada | Auto-finish booking yang jadwalnya lewat |
| Scheduler di `console.php` | ✅ Sudah ada | `bookings:finish` berjalan tiap menit |
| Seeder database | ✅ Sudah ada | Akun default untuk semua role |
| Project Railway | ⚙️ Belum | Perlu buat project di Railway |
| Database MySQL Railway | ⚙️ Belum | Perlu tambah service MySQL |
| Variabel environment | ⚙️ Belum | Perlu isi di Railway (APP_KEY, DB, Midtrans, Cloudinary) |
| Domain | ⚙️ Belum | Perlu generate di Railway |
| Worker scheduler | ⚙️ Belum | Perlu buat service terpisah atau gabung di start command |
| Kredensial Midtrans production | ⚙️ Belum | Perlu ambil dari dashboard Midtrans |
| Webhook Midtrans | ⚙️ Belum | Perlu set notification URL di dashboard Midtrans |

## Pemecahan Masalah (Troubleshooting)

| Masalah | Solusi |
|---------|--------|
| **Error 500 saat deploy** | Cek apakah `APP_KEY` sudah di-set. Jalankan `railway logs` untuk lihat error detail. |
| **Koneksi database ditolak** | Pastikan `DB_HOST` pakai variabel referensi Railway `${{MySQL.MYSQLHOST}}`, bukan nilai hardcode. |
| **Aset tidak muncul (404)** | Pastikan `npm run build` berhasil di log build. Cek `NIXPACKS_PHP_ROOT_DIR=/app/public`. |
| **Migrasi gagal** | Jalankan `railway run php artisan migrate:status` untuk cek status. Pastikan service MySQL berjalan. |
| **Callback Midtrans 404** | Pastikan route caching berhasil. Cek `railway run php artisan route:list` untuk route callback. |
| **Upload Cloudinary gagal** | Pastikan format `CLOUDINARY_URL`: `cloudinary://key:secret@cloud_name`. |
| **Booking tidak otomatis finished** | Pastikan service scheduler berjalan dengan `php artisan schedule:work`. Cek logs service scheduler di Railway. |
| **Callback Midtrans gagal (signature mismatch)** | Pastikan `MIDTRANS_SERVER_KEY` di Railway sama dengan yang di dashboard Midtrans production. |
| **Midtrans masih sandbox** | Set `MIDTRANS_IS_PRODUCTION=true` dan ganti Server Key/Client Key ke yang production. |
| **Build timeout** | Railway free tier punya batas build. Upgrade plan atau pastikan `--no-dev` di Composer. |

### Melihat Log

```bash
# Log deployment
railway logs

# Log build (cek dari dashboard → Deployments → klik build yang ingin dilihat)
```
