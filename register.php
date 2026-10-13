<?php
session_set_cookie_params(['lifetime'=>1800,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
session_start();
include 'koneksi.php';
include 'helper.php';

$error = '';
$success = '';

if (isset($_POST['register'])) {
    verifyCsrfToken();
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $level = 'mahasiswa';
    
    if (empty($username) || empty($password)) {
        $error = "Username dan password tidak boleh kosong!";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $cek = $stmt->get_result();
        $stmt->close();
        
        if ($cek->num_rows > 0) {
            $error = "Username sudah terdaftar!";
        } else {
            $pass_hash = hashPassword($password);
            $stmt = $conn->prepare("INSERT INTO users (username, password, level, nama_lengkap, npm, kelas) VALUES (?, ?, ?, '', '', '')");
            $stmt->bind_param("sss", $username, $pass_hash, $level);
            if ($stmt->execute()) {
                $success = "<script>alert('Pendaftaran Berhasil! Silakan Login.'); window.location='login.php';</script>";
            } else {
                $error = "Gagal mendaftar, coba lagi!";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - SPK Pemilihan Tema TA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeInDown 0.8s ease-out;
        }

        .register-container {
            width: 100%;
            max-width: 900px;
            padding: 20px;
        }

        .register-box {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            display: flex;
            animation: fadeInUp 0.8s ease-out;
        }

        .register-visual {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 45%;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-visual::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: bounce 3s infinite;
        }

        .register-visual i {
            font-size: 80px;
            margin-bottom: 20px;
            color: #ffd700;
            z-index: 1;
            animation: bounce 2s infinite;
        }

        .register-visual h2 {
            font-weight: 800;
            font-size: 2rem;
            z-index: 1;
            margin-bottom: 10px;
        }

        .register-visual p {
            opacity: 0.95;
            z-index: 1;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .register-form-container {
            width: 55%;
            padding: 60px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .register-form-container h3 {
            color: #2c3e50;
            font-weight: 800;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .register-form-container p {
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        .form-label {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .input-group .form-control {
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            font-size: 1rem;
        }

        .input-group .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            color: #667eea;
            font-weight: 600;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 14px;
            font-weight: 700;
            font-size: 1rem;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
            margin-top: 10px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(102, 126, 234, 0.4);
        }

        .register-footer {
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .register-footer a {
            color: #667eea;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .register-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .alert {
            animation: slideIn 0.4s ease-out;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .register-box {
                flex-direction: column;
            }

            .register-visual {
                width: 100%;
                padding: 40px 30px;
            }

            .register-form-container {
                width: 100%;
                padding: 40px 30px;
            }

            .register-visual h2 {
                font-size: 1.5rem;
            }

            .register-form-container h3 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="register-box">
        <div class="register-visual">
            <i class="fas fa-graduation-cap"></i>
            <h2>SPK TEMA TA</h2>
            <p>Sistem Pendukung Keputusan Pemilihan Tema Tugas Akhir Mahasiswa Informatika</p>
            <div class="mt-5 small" style="opacity: 0.7; z-index: 1;">© 2026 Universitas PGRI Semarang</div>
        </div>

        <div class="register-form-container">
            <div>
                <h3>Buat Akun Baru</h3>
                <p>Daftar sebagai mahasiswa untuk menggunakan sistem ini.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <div class="form-group">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                    </div>
                </div>

                <button type="submit" name="register" class="btn-register w-100">
                    <i class="fas fa-user-plus me-2"></i>DAFTAR SEKARANG
                </button>

                <div class="register-footer">
                    Sudah punya akun? <a href="login.php">Masuk di sini</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($success)) echo $success; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>