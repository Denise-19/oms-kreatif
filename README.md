# OMS Kreatif — Order Management System

Platform e-commerce berbasis Laravel 8.4 dengan arsitektur event-driven, queue asynchronous, dan integrasi multi public API.

---

## Tech Stack

| Layer | Teknologi |
|---|---|
| Framework | Laravel 13 (PHP 8.4) |
| Database | MySQL |
| Queue / Cache | Database queue (Redis opsional) |
| Testing | PHPUnit 12 |
| Code Style | Laravel Pint |

---

## Persyaratan

- PHP 8.4+ dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`
- MySQL 5.7+ atau MariaDB 10.3+
- Composer 2+
- Node.js & NPM (opsional, untuk asset frontend)

---

## Instalasi

### 1. Clone repository

```bash
git clone <repo-url>
cd oms-kreatif
```

### 2. Install dependensi PHP

```bash
composer install
```

### 3. Salin file environment

```bash
cp .env.example .env
```

### 4. Generate application key

```bash
php artisan key:generate
```

### 5. Konfigurasi database

Edit file `.env`, sesuaikan kredensial database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oms_kreatif
DB_USERNAME=root
DB_PASSWORD=
```

Pastikan database `oms_kreatif` sudah dibuat:

```sql
CREATE DATABASE oms_kreatif CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 6. Konfigurasi queue

Untuk development, gunakan database queue (tidak perlu Redis):

```env
QUEUE_CONNECTION=database
CACHE_STORE=database
```

### 7. Jalankan migration

```bash
php artisan migrate
```

---

## Menjalankan Aplikasi

### Terminal 1 — Web server

```bash
php artisan serve
```

Aplikasi tersedia di: `http://localhost:8000`

### Terminal 2 — Queue worker (wajib untuk payment processing)

```bash
php artisan queue:work database --queue=payments --timeout=60
```

> Worker **harus berjalan** agar webhook payment diproses secara asynchronous. Tanpa worker, status order tidak akan berubah setelah webhook dikirim.

---


## Postman Collection

Import file `OMS-Kreatif.postman_collection.json` ke Postman.

Set variable collection:

| Variable | Value |
|---|---|
| `base_url` | `http://localhost:8000` |
| `order_id` | *(otomatis terisi setelah Create Order)* |
| `payment_ref` | *(otomatis terisi setelah Initiate Payment)* |

---

## Alur Penggunaan API

### Lifecycle Order: CREATED → COMPLETED

```
CREATED → PENDING_PAYMENT → PAID → PROCESSING → SHIPPED → COMPLETED
                                                         ↘ FAILED
                         ↘ CANCELLED (dari semua status kecuali terminal)
```

#### Step 1 — Ambil daftar produk
```
GET /api/products
```
Catat `external_id` dan `source` dari produk yang ingin dipesan.

---

#### Step 2 — Buat order
```
POST /api/orders
```
```json
{
  "user_id": 1,
  "items": [
    {
      "product_id": "1",
      "source_api": "fakestore",
      "quantity": 2
    }
  ],
  "shipping": {
    "courier": "jne",
    "service": "REG",
    "origin": "Jakarta",
    "destination": "Bandung",
    "weight": 1000
  }
}
```
Response menyertakan `id` order → simpan sebagai `order_id`.

**Status: `created`**

---

#### Step 3 — Inisiasi pembayaran
```
POST /api/orders/{order_id}/pay
```
```json
{
  "provider": "mock_gateway"
}
```
Response menyertakan `external_reference` → simpan sebagai `payment_ref`.

**Status: `pending_payment`**

---

#### Step 4 — Preview mock checkout (opsional)
```
GET /api/mock-checkout/{payment_ref}
```
Menampilkan detail payment dan contoh payload webhook yang siap dipakai.

---

#### Step 5 — Simulasi webhook payment sukses

> Pastikan **queue worker sudah berjalan** di terminal terpisah sebelum langkah ini.

```
POST /api/webhooks/payment
```
```json
{
  "external_reference": "{{payment_ref}}",
  "status": "success",
  "transaction_id": "TXN-DEMO-001",
  "amount": 10219.90
}
```
Response langsung `200` — job dimasukkan ke queue dan diproses worker secara async.

**Status: `paid` → `processing`** *(auto via listener)*

---

#### Step 6 — Tandai order dikirim
```
POST /api/orders/{order_id}/ship
```
```json
{
  "tracking_number": "JNE-1234567890",
  "reason": "Package handed to courier"
}
```
**Status: `shipped`**

---

#### Step 7 — Tandai order selesai
```
POST /api/orders/{order_id}/complete
```
```json
{
  "reason": "Customer confirmed delivery"
}
```
**Status: `completed`** ✅

---

#### Step 8 — Lihat riwayat status
```
GET /api/orders/{order_id}/status-history
```
Menampilkan audit trail lengkap semua transisi status.

---

### Alternate: Pembayaran gagal

Ganti Step 5 dengan:
```json
{
  "external_reference": "{{payment_ref}}",
  "status": "failed",
  "reason": "Insufficient funds"
}
```
**Status: `failed`** (terminal)

---

### Alternate: Pembatalan order

Bisa dilakukan dari status `created`, `pending_payment`, `paid`, atau `processing`:
```
POST /api/orders/{order_id}/cancel
```
```json
{
  "reason": "Customer requested cancellation"
}
```
**Status: `cancelled`** — stok otomatis dikembalikan.

---

## Daftar Endpoint API

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/api/products` | Daftar produk dari FakeStore + DummyJSON |
| `POST` | `/api/orders` | Buat order baru |
| `POST` | `/api/orders/{order}/pay` | Inisiasi pembayaran |
| `POST` | `/api/orders/{order}/process` | Transisi PAID → PROCESSING (manual) |
| `POST` | `/api/orders/{order}/ship` | Transisi PROCESSING → SHIPPED |
| `POST` | `/api/orders/{order}/complete` | Transisi SHIPPED → COMPLETED |
| `POST` | `/api/orders/{order}/cancel` | Batalkan order |
| `GET` | `/api/orders/{order}/status-history` | Riwayat status order |
| `POST` | `/api/webhooks/payment` | Callback webhook dari payment gateway |
| `GET` | `/api/mock-checkout/{reference}` | Preview mock payment gateway |

---

## Konfigurasi Opsional

### RajaOngkir (ongkir real)

Tambahkan API key di `.env` untuk kalkulasi ongkir menggunakan RajaOngkir. Tanpa key, sistem menggunakan formula fallback:

```env
RAJAONGKIR_API_KEY=your_api_key_here
```

### Redis (queue production)

Untuk environment production, ganti ke Redis:

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CLIENT=predis
```

---

## Catatan Penting

- **Queue worker wajib berjalan** untuk memproses webhook payment. Setelah stop/start worker, pastikan jalankan `php artisan config:clear` terlebih dahulu jika ada perubahan `.env`.
- **Idempotency**: Create Order mendukung header `X-Idempotency-Key` untuk mencegah double order. Initiate Payment juga idempotent — memanggil dua kali mengembalikan payment yang sama selama masih `pending`.
- **Concurrency**: Deduct stock menggunakan optimistic locking (`version` column) dengan 3x retry otomatis untuk mencegah race condition.
