# Deploy Born Angel API dengan Bantuan Claude Code

Panduan singkat untuk deploy ke Railway **dengan bantuan Claude Code**. Kamu cukup siapkan beberapa hal, sisanya biar Claude yang kerjakan.

---

## Cara Kerja

```
Kamu siapkan kredensial → Kasih ke Claude → Claude deploy semuanya → Kamu verifikasi
```

Claude Code punya akses ke Railway MCP tools yang bisa:
- Buat project Railway
- Tambah database MySQL
- Set semua environment variables
- Deploy aplikasi
- Generate domain
- Lihat logs & verifikasi

---

## Yang Perlu Kamu Siapkan Dulu

Ada **5 hal** yang harus kamu siapkan sebelum minta Claude deploy. Claude tidak bisa mengambil ini sendiri karena butuh login ke dashboard masing-masing.

### 1. Login Railway CLI

Jalankan di terminal:

```bash
npm i -g @railway/cli
railway login
```

> Akan membuka browser untuk login. Setelah berhasil, Claude bisa menggunakan Railway CLI.

### 2. Push Repo ke GitHub

Pastikan kode sudah di-push ke GitHub:

```bash
git add .
git commit -m "siap deploy"
git push origin main
```

### 3. Ambil Kredensial Midtrans

1. Buka [dashboard.midtrans.com](https://dashboard.midtrans.com/)
2. Pilih environment **Sandbox** (untuk testing) atau **Production** (untuk live)
3. Buka **Settings** → **Access Keys**
4. Catat 3 nilai ini:
   - **Server Key** — contoh: `Mid-server-xxxxxxxxxxxx`
   - **Client Key** — contoh: `Mid-client-xxxxxxxxxxxx`
   - **Merchant ID** — contoh: `G123456789`

### 4. Ambil Cloudinary URL

1. Buka [console.cloudinary.com](https://console.cloudinary.com/)
2. Di halaman **Dashboard**, cari bagian **API Environment Variable**
3. Salin URL-nya — formatnya: `cloudinary://123456789012345:abcdefghijk@cloud-name`

### 5. Generate APP_KEY

Jalankan di terminal (di folder project):

```bash
php artisan key:generate --show
```

Salin hasilnya — formatnya: `base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=`

---

## Minta Claude Deploy

Setelah 5 hal di atas siap, buka Claude Code di terminal dan kirim pesan seperti ini:

```
Tolong deploy Born Angel API ke Railway. Ini kredensialnya:

- APP_KEY: base64:xxxxxxxxxxxxxxxxxxxxxxxx=
- MIDTRANS_SERVER_KEY: Mid-server-xxxxxxxxxxxx
- MIDTRANS_CLIENT_KEY: Mid-client-xxxxxxxxxxxx
- MIDTRANS_MERCHANT_ID: G123456789
- MIDTRANS_IS_PRODUCTION: false (atau true kalau sudah production)
- CLOUDINARY_URL: cloudinary://123456:abcdef@cloud-name

Tolong sekalian:
1. Buat project Railway
2. Tambah MySQL
3. Set semua environment variables
4. Deploy
5. Generate domain
6. Kasih tahu URL-nya kalau sudah selesai
```

> **Catatan keamanan:** Kredensial ini hanya diproses lokal oleh Claude Code di terminal kamu. Tapi tetap jangan share screenshot percakapan yang berisi key/secret.

---

## Yang Claude Akan Lakukan

Setelah menerima kredensial, Claude akan menjalankan langkah-langkah ini secara otomatis:

| Langkah | Apa yang Dilakukan | Tool yang Dipakai |
|---------|--------------------|--------------------|
| 1 | Cek status Railway CLI | `check-railway-status` |
| 2 | Buat project Railway | `create-project-and-link` |
| 3 | Tambah database MySQL | `deploy-template` (MySQL) |
| 4 | Set semua environment variables | `set-variables` |
| 5 | Deploy aplikasi | `deploy` |
| 6 | Generate domain | `generate-domain` |
| 7 | Cek logs untuk verifikasi | `get-logs` |
| 8 | Kasih tahu URL production | — |

Estimasi waktu: **3-5 menit** (tergantung kecepatan build Railway).

---

## Setelah Deploy: Yang Harus Kamu Lakukan Manual

Setelah Claude selesai deploy, ada **2 hal** yang tetap harus kamu kerjakan sendiri di dashboard Midtrans:

### 1. Set Webhook Midtrans

1. Buka [dashboard.midtrans.com](https://dashboard.midtrans.com/)
2. Buka **Settings** → **Configuration**
3. Isi **Payment Notification URL** dengan:
   ```
   https://[domain-dari-claude].up.railway.app/api/payments/callback
   ```
4. Klik **Update**

### 2. Verifikasi

Coba endpoint ini untuk memastikan semuanya jalan:

```bash
# Cek API bisa diakses
curl https://[domain-kamu].up.railway.app/api/services

# Cek register
curl -X POST https://[domain-kamu].up.railway.app/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"test@test.com","password":"password","password_confirmation":"password"}'

# Cek login
curl -X POST https://[domain-kamu].up.railway.app/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"password"}'
```

Atau bisa juga minta Claude: _"Tolong verifikasi apakah deploy-nya berhasil"_ — Claude bisa cek logs Railway.

---

## Mau Setup Worker Scheduler Juga?

Kalau mau booking otomatis berubah status ke `finished` setelah jadwal lewat, minta Claude:

```
Tolong buatkan service scheduler terpisah di Railway untuk menjalankan
php artisan schedule:work. Pakai environment variables yang sama dengan
service utama.
```

Atau alternatif lebih simpel, minta Claude ubah start command:

```
Tolong ubah start command di nixpacks.toml supaya scheduler jalan
di background bersamaan dengan server.
```

---

## Troubleshooting

| Masalah | Minta Claude |
|---------|--------------|
| Deploy gagal | _"Cek logs build terakhir, kenapa gagal?"_ |
| Error 500 | _"Cek logs deploy, ada error apa?"_ |
| Database tidak konek | _"Cek variabel DB di Railway, sudah benar?"_ |
| Mau seed database | _"Tolong jalankan db:seed di Railway"_ |
| Mau lihat variabel | _"Tampilkan semua environment variables di Railway"_ |
| Mau redeploy | _"Tolong deploy ulang"_ |

---

## Ringkasan

| Siapa | Apa |
|-------|-----|
| **Kamu** | Login Railway CLI, push repo, siapkan 5 kredensial, set webhook Midtrans |
| **Claude** | Buat project, tambah MySQL, set env vars, deploy, generate domain, verifikasi |

Total waktu kamu: **~10 menit** persiapan.
Total waktu Claude: **~5 menit** deploy.
