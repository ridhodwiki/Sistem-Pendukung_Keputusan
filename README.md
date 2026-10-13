 # 🎓 Sistem Pendukung Keputusan Pemilihan Tema Tugas Akhir

  Aplikasi web berbasis **Weighted Sum Model (WSM)** untuk membantu mahasiswa
  menentukan prioritas tema Tugas Akhir di Program Studi Informatika
  Universitas PGRI Semarang.

  ## ✨ Fitur

  - 🔐 Autentikasi multi-role (Admin & Mahasiswa)
  - 📊 Perhitungan ranking WSM otomatis
  - 📝 Input nilai per kriteria dengan slider interaktif
  - 📋 Manajemen data mahasiswa, tema, dan kriteria
  - 📁 Export ranking ke CSV
  - 🔒 Isolasi data — mahasiswa hanya melihat data milik sendiri
  - 📜 Activity log untuk monitoring aktivitas sistem

  ## 🛠️ Tech Stack

  - **Backend:** PHP Native
  - **Database:** MySQL (MySQLi)
  - **Frontend:** Bootstrap 5, Font Awesome 6
  - **Server:** Apache (XAMPP)

  ## ⚙️ Cara Setup Lokal

  ### Prasyarat
  - XAMPP (PHP 7.4+ & MySQL)
  - Git

  ### Langkah Instalasi

  1. **Clone repo ini**
     ```bash
     git clone https://github.com/ridhodwiki/Sistem-Pendukung_Keputusan.git
     cd Sistem-Pendukung_Keputusan

  2. Pindahkan ke folder htdocs

    xampp/htdocs/spk_tugas_akhir/

  3. Buat file koneksi database

    copy koneksi.example.php koneksi.php

    Lalu buka koneksi.php dan sesuaikan:

    $host = "localhost";
    $user = "root";        // username MySQL kamu
    $pass = "";            // password MySQL kamu
    $db   = "db_spk_ta";  // nama database

  4. Import database
    - Buka http://localhost/phpmyadmin
    - Buat database baru bernama db_spk_ta
    - Import file database_update.sql

  5. Jalankan aplikasi

    http://localhost/spk_tugas_akhir

  👤 Akun Default

  ┌───────────┬──────────────────┬──────────┐
  │ Role      │ Username         │ Password │
  ├───────────┼──────────────────┼──────────┤
  │ Admin     │ admin            │ admin    │
  ├───────────┼──────────────────┼──────────┤
  │ Mahasiswa │ (daftar sendiri) │ —        │
  └───────────┴──────────────────┴──────────┘

  │ ⚠️ Segera ganti password admin setelah login pertama.

  📁 Struktur File

  spk_tugas_akhir/
  ├── index.php           # Dashboard & hasil ranking WSM
  ├── login.php           # Halaman login
  ├── register.php        # Pendaftaran mahasiswa
  ├── admin.php           # Kelola kriteria & tema (admin)
  ├── input_nilai.php     # Input nilai alternatif
  ├── data_mahasiswa.php  # Manajemen mahasiswa (admin)
  ├── profil.php          # Pengaturan profil
  ├── export.php          # Export CSV
  ├── activity_log.php    # Log aktivitas (admin)
  ├── helper.php          # Fungsi keamanan & utilitas
  ├── koneksi.example.php # Template konfigurasi database
  └── assets/
      └── style.css       # Custom styling

  🔒 Keamanan

  - SQL Injection: prepared statements di semua query
  - XSS: htmlspecialchars pada semua output
  - CSRF: token di semua form POST
  - Session: HttpOnly cookie, timeout 30 menit, regenerasi ID
  - Password: bcrypt (auto-upgrade dari MD5 legacy)

  📖 Metode WSM

  Skor dihitung dengan rumus:

  S = Σ (nilai_kriteria × bobot_kriteria)

  - Nilai per kriteria: skala 1–5
  - Total bobot semua kriteria harus = 1.0
  - Tema dengan skor tertinggi = rekomendasi terbaik

  📄 Lisensi

  Proyek ini dibuat untuk keperluan Tugas Akhir.
  © 2026 Universitas PGRI Semarang


  ---
