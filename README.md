# Born Angel API

REST API untuk platform booking layanan kecantikan/makeup. Dibangun dengan Laravel 12, PHP 8.2+, autentikasi Sanctum, pembayaran Midtrans, dan upload gambar Cloudinary.

## Tech Stack

- **Framework:** Laravel 12
- **PHP:** 8.2+
- **Auth:** Laravel Sanctum (token-based)
- **Database:** MySQL (production) / SQLite in-memory (testing)
- **Payment:** Midtrans Snap
- **Image Storage:** Cloudinary
- **Frontend Build:** Vite + Tailwind CSS 4.0
- **Testing:** PHPUnit 11.5
- **Linting:** Laravel Pint

## Quick Start

### Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm
- MySQL

### Installation

```bash
# Clone repository
git clone https://github.com/your-username/born-angel-api.git
cd born-angel-api

# Full setup (install deps, copy .env, generate key, migrate, build frontend)
composer setup

# Atau manual
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

### Konfigurasi Environment

Salin `.env.example` ke `.env` dan isi variabel berikut:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=born_angel_db
DB_USERNAME=root
DB_PASSWORD=

MIDTRANS_SERVER_KEY=your-server-key
MIDTRANS_CLIENT_KEY=your-client-key

CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
```

### Menjalankan Development Server

```bash
# Jalankan server, queue worker, log streaming, dan Vite secara bersamaan
composer dev
```

Server berjalan di `http://127.0.0.1:8000`.

## Akun Default (Seeder)

| Role | Email | Password |
|------|-------|----------|
| Super Admin | `superadmin@example.com` | `password` |
| Admin | `admin@example.com` | `password` |
| Instructor | `instructor@example.com` | `password` |
| User | `user@example.com` | `password` |

## Arsitektur

### Role-Based Access Control (RBAC)

4 level role dengan hierarki ketat:

```
super_admin > admin > instructor > user
```

- **super_admin** — Akses penuh, kelola admin, tidak bisa dihapus (User ID 1 dilindungi)
- **admin** — Kelola layanan, jadwal, instruktur, user, laporan
- **instructor** — Lihat jadwal & review milik sendiri
- **user** — Booking, review, kelola profil

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

### Context-Aware Endpoints

Beberapa endpoint mengembalikan data berbeda berdasarkan role user yang login:

| Endpoint | User | Instructor | Admin |
|----------|------|------------|-------|
| `GET /api/schedules` | Jadwal upcoming | Jadwal milik sendiri | Semua jadwal |
| `GET /api/bookings` | Booking milik sendiri | — | Semua booking |
| `GET /api/reviews` | Semua review | Review untuk kelasnya | Semua review |

### Fitur Teknis

- **Pessimistic locking** pada pembuatan booking (`lockForUpdate()` dalam `DB::transaction()`) untuk mencegah race condition pada slot
- **Soft deletes** pada model User, Service, Instructor, Schedule, Booking
- **Auto-finish bookings** — Scheduler (`bookings:finish`) berjalan tiap menit untuk otomatis mengubah status booking menjadi `finished` setelah jadwal lewat

## API Endpoints

### Public (Tanpa Auth)

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/register` | Registrasi user baru |
| POST | `/api/login` | Login, mendapat token |
| GET | `/api/services` | Daftar semua layanan |
| GET | `/api/services/{id}` | Detail layanan |
| GET | `/api/instructors` | Daftar semua instruktur |
| GET | `/api/instructors/{id}` | Detail instruktur |
| GET | `/api/schedules` | Jadwal tersedia |
| GET | `/api/schedules/{id}` | Detail jadwal |
| GET | `/api/testimonials` | Review untuk homepage |
| POST | `/api/payments/callback` | Midtrans webhook |

### Authenticated (Perlu Token)

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/logout` | Logout, revoke token |
| GET | `/api/profile` | Lihat profil |
| PUT | `/api/profile` | Update profil |
| DELETE | `/api/profile` | Hapus akun |
| GET | `/api/bookings` | Daftar booking |
| POST | `/api/bookings` | Buat booking baru |
| POST | `/api/bookings/{id}/cancel` | Batalkan booking |
| GET | `/api/payments/snap-token/{booking_id}` | Dapat Midtrans Snap token |
| GET | `/api/reviews` | Daftar review |
| POST | `/api/reviews` | Buat review |
| PUT | `/api/reviews/{id}` | Update review |
| DELETE | `/api/reviews/{id}` | Hapus review |

### Admin & Super Admin

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/admin/dashboard/stats` | Statistik dashboard |
| GET | `/api/users` | Daftar user |
| POST | `/api/users` | Buat akun admin/instruktur |
| GET | `/api/users/{id}` | Detail user |
| PUT | `/api/users/{id}` | Update user |
| DELETE | `/api/users/{id}` | Hapus user |
| POST | `/api/services` | Buat layanan |
| PUT | `/api/services/{id}` | Update layanan |
| DELETE | `/api/services/{id}` | Hapus layanan |
| POST | `/api/instructors` | Buat profil instruktur |
| PUT | `/api/instructors/{id}` | Update instruktur |
| DELETE | `/api/instructors/{id}` | Hapus instruktur |
| POST | `/api/schedules` | Buat jadwal |
| PUT | `/api/schedules/{id}` | Update jadwal |
| DELETE | `/api/schedules/{id}` | Hapus jadwal |
| GET | `/api/reports/revenue` | Laporan pendapatan |
| GET | `/api/reports/services-performance` | Performa layanan |
| GET | `/api/reports/operational-stats` | Statistik operasional |
| GET | `/api/reports/instructor-performance` | Performa instruktur |
| GET | `/api/reports/peak-hours` | Analisis jam sibuk |

## Alur Pembayaran (Midtrans)

```
1. User buat booking         → POST /api/bookings (status: pending)
2. Ambil Snap token          → GET /api/payments/snap-token/{booking_id}
3. User bayar via Midtrans   → (redirect ke Midtrans Snap)
4. Midtrans kirim callback   → POST /api/payments/callback
5. Status booking terupdate  → confirmed / cancelled
6. Jadwal lewat              → Scheduler otomatis ubah ke finished
```

Metode pembayaran yang didukung:
- Credit Card (3D Secure)
- GoPay, ShopeePay, QRIS
- Bank Transfer (BCA VA, BNI VA, BRI VA, Permata VA)

## Upload Gambar (Cloudinary)

- **Gambar layanan** — disimpan di folder `services/`
- **Foto instruktur** — disimpan di folder `instructors/`
- **Maks ukuran:** 5MB
- **Format:** JPEG, PNG, JPG, GIF

## Perintah Umum

```bash
# Development server
composer dev

# Jalankan test
composer test

# Test spesifik
php artisan test --filter=NamaTest

# Lint kode
./vendor/bin/pint

# Migrasi database
php artisan migrate

# Reset & seed ulang
php artisan migrate:fresh --seed

# Interactive debugging
php artisan tinker

# Jalankan scheduler (production)
php artisan schedule:work
```

## Testing

```bash
# Jalankan semua test
composer test

# Jalankan test spesifik
php artisan test --filter=ExampleTest
```

Test menggunakan SQLite in-memory sehingga tidak mempengaruhi database development.

## Postman Collection

Koleksi Postman tersedia di folder `postman/` dengan fitur:

- Semua endpoint sudah terkonfigurasi
- Token otomatis tersimpan saat login dan terhapus saat logout
- Environment variables terpisah

Lihat [`postman/README.md`](postman/README.md) untuk panduan lengkap.

## Deployment

Panduan deployment ke Railway tersedia di [`docs/DEPLOYMENT_RAILWAY.md`](docs/DEPLOYMENT_RAILWAY.md).

## Struktur Project

```
born-angel-api/
├── app/
│   ├── Console/Commands/       # Artisan commands (FinishCompletedBookings)
│   ├── Http/
│   │   ├── Controllers/Api/    # 11 API controllers
│   │   └── Middleware/          # EnsureUserHasRole (RBAC)
│   └── Models/                 # 7 Eloquent models
├── config/
│   ├── midtrans.php            # Konfigurasi Midtrans
│   └── cloudinary.php          # Konfigurasi Cloudinary
├── database/
│   ├── migrations/             # 13 migration files
│   ├── seeders/                # Database seeders
│   └── factories/              # Model factories
├── docs/
│   └── DEPLOYMENT_RAILWAY.md   # Panduan deployment
├── postman/                    # Postman collection & environment
├── routes/
│   ├── api.php                 # Semua API routes
│   └── console.php             # Scheduler (bookings:finish)
├── nixpacks.toml               # Konfigurasi build Railway
└── CONTROLLER_RBAC.md          # Matriks akses kontrol
```
