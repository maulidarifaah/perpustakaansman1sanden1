# 📚 Perpustakaan Sekolah SMA N 1 Sanden

Aplikasi web peminjaman dan pengembalian buku untuk Perpustakaan SMA N 1 Sanden. Dibangun dengan PHP native (MySQLi) dan MySQL, di-hosting di InfinityFree.

🔗 **Live:** [perputakaansman1sanden.infinityfreeapp.com](https://perputakaansman1sanden.infinityfreeapp.com)

---

## ✨ Fitur

- **Multi-role login** — Siswa/Anggota, Petugas, dan Admin punya alur login & dashboard terpisah, dengan session yang tidak saling bentrok (cookie session berbeda per peran).
- **Katalog buku online** — telusuri koleksi berdasarkan judul, penulis, atau kategori.
- **Peminjaman & pengembalian tercatat** — setiap transaksi tersimpan dan terhubung ke akun anggota.
- **Perhitungan denda otomatis** — denda keterlambatan dihitung berdasarkan jumlah hari terlambat.
- **Perpanjangan peminjaman** — dengan batas maksimal jumlah perpanjangan per transaksi.
- **Dashboard statistik** — total eksemplar buku, anggota aktif, buku sedang dipinjam, peminjaman terlambat, serta grafik riwayat peminjaman 6 bulan terakhir (Chart.js).

## 🧩 Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | PHP (native, MySQLi + prepared statements) |
| Database | MySQL |
| Frontend | HTML, CSS custom (tema "paper/kartu perpustakaan"), Chart.js |
| Font | Fraunces (display), Inter (body), Courier Prime (mono) |
| Hosting | InfinityFree |

## 📁 Struktur File Utama

```
├── index.php              # Landing page + dashboard statistik (butuh DB)
├── index1.php              # Landing page versi tanpa statistik
├── login.php               # Halaman login (tab Siswa/Petugas/Admin)
├── login_proses.php        # Handler proses login (query DB, set session)
├── seed.php                # Script sekali-jalan untuk isi data awal (admin/petugas/siswa contoh)
├── config/
│   └── db.php               # Koneksi database + konfigurasi denda & masa pinjam
├── Functions.php           # Fungsi bantu: hitung denda, format Rupiah, format tanggal Indo, dll.
├── admin/                  # Dashboard & fitur khusus Admin
├── petugas/                 # Dashboard & fitur khusus Petugas
├── siswa/                   # Dashboard, katalog, & daftar akun untuk Siswa/Anggota
└── assets/
    ├── css/, img/, video/
```

> Ada juga versi **HTML statis** (`index.html`, `index1.html`, `login.html`) tanpa backend — dipakai untuk preview desain tanpa perlu server PHP aktif. Statistik/grafik di versi ini pakai **data contoh**, bukan data asli. File `login_proses.php` dan `seed.php` tidak punya versi HTML karena murni logika backend tanpa tampilan.

## ⚙️ Instalasi Lokal (XAMPP)

1. Clone/salin folder project ke `htdocs/`.
2. Import `database.sql` ke MySQL (buat database baru).
3. Isi `config/db.php` dengan kredensial database lokal:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'nama_database_kamu');
   ```
4. Jalankan `seed.php` sekali di browser untuk membuat akun awal (admin, petugas, contoh siswa).
5. **Hapus atau rename `seed.php`** setelah berhasil dijalankan, supaya tidak ter-run ulang tanpa sengaja.
6. Akses `index.php` melalui `http://localhost/nama-folder/`.

## ☁️ Deploy ke InfinityFree

1. Buat database MySQL baru lewat vPanel InfinityFree (menu **MySQL Databases**).
2. Isi `config/db.php` dengan kredensial dari InfinityFree:
   ```php
   define('DB_HOST', 'sql2xx.infinityfree.com');
   define('DB_USER', 'if0_xxxxxxxx');
   define('DB_PASS', 'password_kamu');
   define('DB_NAME', 'if0_xxxxxxxx_perpus');
   ```
3. Upload seluruh file ke `htdocs/`.
4. Import `database.sql` lewat phpMyAdmin InfinityFree.
5. Jalankan `seed.php` sekali lewat URL, lalu hapus filenya.
6. Pastikan `index.php` (bukan `index.html`) yang jadi halaman utama yang diakses.

## 🔑 Akun Default (dari `seed.php`)

| Peran | Username | Password |
|---|---|---|
| Admin | `Maulida` | `242570` |
| Petugas | `petugas1` | `petugas123` |
| Siswa | `siswa1` | `siswa123` |
| Siswa | `siswa2` | `siswa123` |

⚠️ Ganti password default ini setelah login pertama kali, terutama di lingkungan production.

## 🛡️ Catatan Keamanan

- Password disimpan dengan `password_hash()` (bcrypt) dan diverifikasi lewat `password_verify()`.
- Semua query database pakai **prepared statements** (mencegah SQL injection).
- Session untuk Admin dan Siswa dipisah lewat nama session berbeda (`ADMINSESSID` / `SISWASESSID`), jadi bisa login di role berbeda pada tab browser berbeda tanpa saling menimpa.

## 📝 Lisensi

Proyek internal untuk kebutuhan Perpustakaan SMA N 1 Sanden.
# 📚 Perpustakaan Sekolah SMA N 1 Sanden

Aplikasi web peminjaman dan pengembalian buku untuk Perpustakaan SMA N 1 Sanden. Dibangun dengan PHP native (MySQLi) dan MySQL, di-hosting di InfinityFree.

🔗 **Live:** [perputakaansman1sanden.infinityfreeapp.com](https://perputakaansman1sanden.infinityfreeapp.com)

---

## ✨ Fitur

- **Multi-role login** — Siswa/Anggota, Petugas, dan Admin punya alur login & dashboard terpisah, dengan session yang tidak saling bentrok (cookie session berbeda per peran).
- **Katalog buku online** — telusuri koleksi berdasarkan judul, penulis, atau kategori.
- **Peminjaman & pengembalian tercatat** — setiap transaksi tersimpan dan terhubung ke akun anggota.
- **Perhitungan denda otomatis** — denda keterlambatan dihitung berdasarkan jumlah hari terlambat.
- **Perpanjangan peminjaman** — dengan batas maksimal jumlah perpanjangan per transaksi.
- **Dashboard statistik** — total eksemplar buku, anggota aktif, buku sedang dipinjam, peminjaman terlambat, serta grafik riwayat peminjaman 6 bulan terakhir (Chart.js).

## 🧩 Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | PHP (native, MySQLi + prepared statements) |
| Database | MySQL |
| Frontend | HTML, CSS custom (tema "paper/kartu perpustakaan"), Chart.js |
| Font | Fraunces (display), Inter (body), Courier Prime (mono) |
| Hosting | InfinityFree |

## 📁 Struktur File Utama

```
├── index.php              # Landing page + dashboard statistik (butuh DB)
├── index1.php              # Landing page versi tanpa statistik
├── login.php               # Halaman login (tab Siswa/Petugas/Admin)
├── login_proses.php        # Handler proses login (query DB, set session)
├── seed.php                # Script sekali-jalan untuk isi data awal (admin/petugas/siswa contoh)
├── config/
│   └── db.php               # Koneksi database + konfigurasi denda & masa pinjam
├── Functions.php           # Fungsi bantu: hitung denda, format Rupiah, format tanggal Indo, dll.
├── admin/                  # Dashboard & fitur khusus Admin
├── petugas/                 # Dashboard & fitur khusus Petugas
├── siswa/                   # Dashboard, katalog, & daftar akun untuk Siswa/Anggota
└── assets/
    ├── css/, img/, video/
```

> Ada juga versi **HTML statis** (`index.html`, `index1.html`, `login.html`) tanpa backend — dipakai untuk preview desain tanpa perlu server PHP aktif. Statistik/grafik di versi ini pakai **data contoh**, bukan data asli. File `login_proses.php` dan `seed.php` tidak punya versi HTML karena murni logika backend tanpa tampilan.

## ⚙️ Instalasi Lokal (XAMPP)

1. Clone/salin folder project ke `htdocs/`.
2. Import `database.sql` ke MySQL (buat database baru).
3. Isi `config/db.php` dengan kredensial database lokal:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'nama_database_kamu');
   ```
4. Jalankan `seed.php` sekali di browser untuk membuat akun awal (admin, petugas, contoh siswa).
5. **Hapus atau rename `seed.php`** setelah berhasil dijalankan, supaya tidak ter-run ulang tanpa sengaja.
6. Akses `index.php` melalui `http://localhost/nama-folder/`.

## ☁️ Deploy ke InfinityFree

1. Buat database MySQL baru lewat vPanel InfinityFree (menu **MySQL Databases**).
2. Isi `config/db.php` dengan kredensial dari InfinityFree:
   ```php
   define('DB_HOST', 'sql2xx.infinityfree.com');
   define('DB_USER', 'if0_xxxxxxxx');
   define('DB_PASS', 'password_kamu');
   define('DB_NAME', 'if0_xxxxxxxx_perpus');
   ```
3. Upload seluruh file ke `htdocs/`.
4. Import `database.sql` lewat phpMyAdmin InfinityFree.
5. Jalankan `seed.php` sekali lewat URL, lalu hapus filenya.
6. Pastikan `index.php` (bukan `index.html`) yang jadi halaman utama yang diakses.

## 🔑 Akun Default (dari `seed.php`)

| Peran | Username | Password |
|---|---|---|
| Admin | `Maulida` | `242570` |
| Petugas | `petugas1` | `petugas123` |
| Siswa | `siswa1` | `siswa123` |
| Siswa | `siswa2` | `siswa123` |

⚠️ Ganti password default ini setelah login pertama kali, terutama di lingkungan production.

## 🛡️ Catatan Keamanan

- Password disimpan dengan `password_hash()` (bcrypt) dan diverifikasi lewat `password_verify()`.
- Semua query database pakai **prepared statements** (mencegah SQL injection).
- Session untuk Admin dan Siswa dipisah lewat nama session berbeda (`ADMINSESSID` / `SISWASESSID`), jadi bisa login di role berbeda pada tab browser berbeda tanpa saling menimpa.

## 📝 Lisensi

Proyek internal untuk kebutuhan Perpustakaan SMA N 1 Sanden.
