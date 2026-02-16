# Born Angel API

REST API untuk platform booking layanan kecantikan/makeup. Dibangun dengan Laravel 12, PHP 8.2+, autentikasi Sanctum, pembayaran Midtrans, dan upload gambar Cloudinary.

## Teknologi yang Digunakan

- **Framework:** Laravel 12
- **PHP:** 8.2+
- **Autentikasi:** Laravel Sanctum (berbasis token)
- **Database:** MySQL (produksi) / SQLite in-memory (testing)
- **Pembayaran:** Midtrans Snap
- **Penyimpanan Gambar:** Cloudinary
- **Build Frontend:** Vite + Tailwind CSS 4.0
- **Testing:** PHPUnit 11.5
- **Linting:** Laravel Pint

## Cara Memulai

### Prasyarat

- PHP 8.2+
- Composer
- Node.js & npm
- MySQL

### Instalasi

```bash
# Clone repositori
git clone https://github.com/your-username/born-angel-api.git
cd born-angel-api

# Setup otomatis (install dependensi, salin .env, generate key, migrasi, build frontend)
composer setup

# Atau setup manual
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

### Konfigurasi Environment

Salin `.env.example` ke `.env` lalu isi variabel berikut:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=born_angel_db
DB_USERNAME=root
DB_PASSWORD=

MIDTRANS_SERVER_KEY=kunci-server-anda
MIDTRANS_CLIENT_KEY=kunci-client-anda

CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
```

### Menjalankan Server Development

```bash
# Jalankan server, queue worker, log streaming, dan Vite secara bersamaan
composer dev
```

Server berjalan di `http://127.0.0.1:8000`.

## Akun Default (Seeder)

| Peran | Email | Kata Sandi |
|-------|-------|------------|
| Super Admin | `superadmin@example.com` | `password` |
| Admin | `admin@example.com` | `password` |
| Instruktur | `instructor@example.com` | `password` |
| Pengguna | `user@example.com` | `password` |

## Arsitektur

### Kontrol Akses Berbasis Peran (RBAC)

4 level peran dengan hierarki ketat:

```
super_admin > admin > instructor > user
```

- **super_admin** — Akses penuh, kelola admin, tidak bisa dihapus (User ID 1 dilindungi/immutable)
- **admin** — Kelola layanan, jadwal, instruktur, pengguna, laporan. Tidak bisa menyentuh super admin.
- **instructor** — Lihat jadwal & review milik sendiri saja
- **user** — Booking, review, kelola profil sendiri

### Model & Relasi

```
User → hasMany Bookings, Reviews; hasOne Instructor
Service → hasMany Schedules, Instructors
Instructor → belongsTo User, Service; hasMany Schedules
Schedule → belongsTo Service, Instructor; hasMany Bookings
Booking → belongsTo User, Schedule; hasOne Review, Payment
Review → belongsTo Booking
Payment → belongsTo Booking
```

### Entity Relationship Diagram (ERD)

![ERD Born Angel API](erd.png)

### Endpoint Sadar Konteks (Context-Aware)

Beberapa endpoint mengembalikan data berbeda tergantung peran user yang login:

| Endpoint | Publik/User | Instruktur | Admin |
|----------|-------------|------------|-------|
| `GET /api/schedules` | Jadwal yang akan datang | Jadwal milik sendiri (lalu & akan datang) | Semua jadwal |
| `GET /api/bookings` | Booking milik sendiri | — | Semua booking |
| `GET /api/reviews` | Semua review | Review untuk kelas miliknya | Semua review |

### Fitur Teknis

- **Pessimistic locking** — `Schedule::lockForUpdate()` dalam `DB::transaction()` saat membuat booking, mencegah race condition pada ketersediaan slot
- **Soft deletes** — pada model User, Service, Instructor, Schedule, Booking (data tidak benar-benar terhapus)
- **Auto-finish bookings** — Scheduler `bookings:finish` berjalan tiap menit, otomatis mengubah status booking dari `confirmed` ke `finished` setelah `end_time` jadwal lewat

## Daftar API Endpoint

### Publik (Tanpa Autentikasi)

| Metode | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/register` | Registrasi pengguna baru |
| POST | `/api/login` | Login, mendapat token API |
| GET | `/api/services` | Daftar semua layanan |
| GET | `/api/services/{id}` | Detail layanan |
| GET | `/api/instructors` | Daftar semua instruktur |
| GET | `/api/instructors/{id}` | Detail instruktur |
| GET | `/api/schedules` | Daftar jadwal (publik: yang akan datang saja) |
| GET | `/api/schedules/{id}` | Detail jadwal |
| GET | `/api/testimonials` | Daftar review untuk halaman utama |
| POST | `/api/payments/callback` | Webhook notifikasi Midtrans |

### Terotentikasi (Perlu Token Sanctum)

| Metode | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/logout` | Logout, cabut token |
| GET | `/api/profile` | Lihat profil sendiri |
| PUT | `/api/profile` | Perbarui profil |
| DELETE | `/api/profile` | Hapus akun sendiri |
| GET | `/api/test-auth` | Debug: cek status autentikasi |
| GET | `/api/bookings` | Daftar booking (user: milik sendiri, admin: semua) |
| POST | `/api/bookings` | Buat booking baru |
| POST | `/api/bookings/{id}/cancel` | Batalkan booking (pemilik atau admin) |
| GET | `/api/payments/snap-token/{booking_id}` | Dapatkan Midtrans Snap token |
| GET | `/api/reviews` | Daftar review (sadar konteks per peran) |
| POST | `/api/reviews` | Buat review (harus punya booking terkait) |
| PUT | `/api/reviews/{id}` | Perbarui review (pemilik saja) |
| DELETE | `/api/reviews/{id}` | Hapus review (pemilik atau admin) |

### Admin & Super Admin

| Metode | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/admin/dashboard/stats` | Statistik dashboard |
| GET | `/api/users` | Daftar pengguna (mendukung filter `?role=instructor`) |
| POST | `/api/users` | Buat akun admin/instruktur |
| GET | `/api/users/{id}` | Detail pengguna |
| PUT | `/api/users/{id}` | Perbarui pengguna (hierarki ditegakkan) |
| DELETE | `/api/users/{id}` | Hapus pengguna (hierarki ditegakkan) |
| POST | `/api/services` | Buat layanan baru |
| PUT | `/api/services/{id}` | Perbarui layanan |
| DELETE | `/api/services/{id}` | Hapus layanan |
| POST | `/api/instructors` | Buat profil instruktur |
| PUT | `/api/instructors/{id}` | Perbarui profil instruktur |
| DELETE | `/api/instructors/{id}` | Hapus profil instruktur |
| POST | `/api/schedules` | Buat jadwal baru |
| PUT | `/api/schedules/{id}` | Perbarui jadwal |
| DELETE | `/api/schedules/{id}` | Hapus jadwal |
| GET | `/api/reports/revenue` | Laporan pendapatan |
| GET | `/api/reports/services-performance` | Performa layanan |
| GET | `/api/reports/operational-stats` | Statistik operasional |
| GET | `/api/reports/instructor-performance` | Performa instruktur |
| GET | `/api/reports/peak-hours` | Analisis jam sibuk |

## Alur Pembayaran (Midtrans)

```
1. User buat booking           → POST /api/bookings (status: pending)
2. Ambil Snap token            → GET /api/payments/snap-token/{booking_id}
3. User bayar via Midtrans     → (diarahkan ke halaman Midtrans Snap)
4. Midtrans kirim notifikasi   → POST /api/payments/callback
5. Status booking diperbarui   → confirmed / cancelled
6. Jadwal kelas lewat          → Scheduler otomatis ubah ke finished
```

Metode pembayaran yang didukung:

- Kartu Kredit (3D Secure)
- GoPay, ShopeePay, QRIS
- Transfer Bank (BCA VA, BNI VA, BRI VA, Permata VA)

## Upload Gambar (Cloudinary)

- **Gambar layanan** — disimpan di folder `services/`
- **Foto instruktur** — disimpan di folder `instructors/`
- **Ukuran maksimal:** 5MB
- **Format yang diterima:** JPEG, PNG, JPG, GIF

## Perintah-Perintah Umum

```bash
# Jalankan server development (server + queue + log + Vite)
composer dev

# Jalankan semua test
composer test

# Jalankan test tertentu
php artisan test --filter=NamaTest

# Periksa gaya kode (linting)
./vendor/bin/pint

# Migrasi database
php artisan migrate

# Reset database & isi ulang data seeder
php artisan migrate:fresh --seed

# Debugging interaktif
php artisan tinker

# Jalankan scheduler di production
php artisan schedule:work
```

## Pengujian (Testing)

```bash
# Jalankan semua test
composer test

# Jalankan test tertentu
php artisan test --filter=NamaTest
```

Test menggunakan SQLite in-memory sehingga tidak mempengaruhi database development. Konfigurasi test ada di `phpunit.xml`.

## Koleksi Postman

Koleksi Postman tersedia di folder `postman/` dengan fitur:

- Semua endpoint sudah terkonfigurasi dan siap digunakan
- Token otomatis tersimpan saat login dan terhapus saat logout
- Variabel environment terpisah (`base_url`, `token`, `user_role`, `user_id`, `my_instructor_id`)

Lihat [`postman/README.md`](postman/README.md) untuk panduan penggunaan lengkap.

## Deployment

Panduan deployment ke Railway tersedia di [`docs/DEPLOYMENT_RAILWAY.md`](docs/DEPLOYMENT_RAILWAY.md), mencakup:

- Setup project & database MySQL di Railway
- Konfigurasi Nixpacks (`nixpacks.toml`)
- Variabel environment untuk produksi
- Setup worker scheduler (auto-finish bookings)
- Konfigurasi Midtrans production & webhook
- Domain & troubleshooting

## Struktur Project

```
born-angel-api/
├── app/
│   ├── Console/Commands/          # Command artisan (FinishCompletedBookings)
│   ├── Http/
│   │   ├── Controllers/Api/       # 11 controller API
│   │   └── Middleware/             # EnsureUserHasRole (RBAC)
│   └── Models/                    # 7 model Eloquent
├── config/
│   ├── midtrans.php               # Konfigurasi Midtrans
│   └── cloudinary.php             # Konfigurasi Cloudinary
├── database/
│   ├── migrations/                # 13 file migrasi
│   ├── seeders/                   # Seeder database
│   └── factories/                 # Factory untuk testing
├── docs/
│   └── DEPLOYMENT_RAILWAY.md      # Panduan deployment Railway
├── postman/                       # Koleksi & environment Postman
├── routes/
│   ├── api.php                    # Semua route API
│   └── console.php                # Penjadwal (bookings:finish)
├── nixpacks.toml                  # Konfigurasi build Railway
├── CONTROLLER_RBAC.md             # Matriks kontrol akses per controller
└── CLAUDE.md                      # Panduan untuk Claude Code
```
