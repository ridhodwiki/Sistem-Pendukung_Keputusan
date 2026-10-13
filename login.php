<?php
session_set_cookie_params(['lifetime'=>1800,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
session_start();
include 'koneksi.php';
include 'helper.php';

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Username dan password tidak boleh kosong!";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, level FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            if (verifyPassword($password, $data['password'])) {
                            if (strlen($data['password']) === 32 && ctype_xdigit($data['password'])) {
                                upgradePasswordHash($conn, $data['id'], $password);
                            }
                
                session_regenerate_id(true);
                $_SESSION['username'] = $data['username'];
                $_SESSION['level'] = $data['level'];
                $_SESSION['user_id'] = $data['id'];
                $_SESSION['login_time'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                
                logActivity($conn, $data['id'], 'LOGIN', 'User login successful');
                
                header("Location: index.php");
                exit();
            } else {
                $error = "Username atau Password salah!";
            }
        } else {
            $error = "Username atau Password salah!";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SPK Pemilihan Tema TA</title>
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

        .login-container {
            width: 100%;
            max-width: 900px;
            padding: 20px;
        }

        .login-box {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            display: flex;
            animation: fadeInUp 0.8s ease-out;
        }

        .login-visual {
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

        .login-visual::before {
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

        .login-visual::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            animation: bounce 4s infinite;
        }

        .login-visual i {
            font-size: 80px;
            margin-bottom: 20px;
            color: #ffd700;
            z-index: 1;
            animation: bounce 2s infinite;
        }

        .login-visual h2 {
            font-weight: 800;
            font-size: 2rem;
            z-index: 1;
            margin-bottom: 10px;
        }

        .login-visual p {
            opacity: 0.95;
            z-index: 1;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .login-form-container {
            width: 55%;
            padding: 60px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-form-container h3 {
            color: #2c3e50;
            font-weight: 800;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .login-form-container p {
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
        .form-group:nth-child(4) { animation-delay: 0.4s; }

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

        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .login-footer a {
            color: #667eea;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .login-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .alert {
            animation: slideIn 0.4s ease-out;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .login-box {
                flex-direction: column;
            }

            .login-visual {
                width: 100%;
                padding: 40px 30px;
            }

            .login-form-container {
                width: 100%;
                padding: 40px 30px;
            }

            .login-visual h2 {
                font-size: 1.5rem;
            }

            .login-form-container h3 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-box">
        <div class="login-visual">
            <i class="fas fa-graduation-cap"></i>
            <h2>SPK TEMA TA</h2>
            <p>Sistem Pendukung Keputusan Pemilihan Tema Tugas Akhir Mahasiswa Informatika</p>
            <div class="mt-5 small" style="opacity: 0.7; z-index: 1;">© 2026 Universitas PGRI Semarang</div>
        </div>

        <div class="login-form-container">
            <div>
                <h3>Selamat Datang</h3>
                <p>Silakan masuk untuk mengelola data atau melihat hasil analisis.</p>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
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
                        <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                    </div>
                </div>

                <button type="submit" name="login" class="btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>MASUK KE SISTEM
                </button>

                <div class="login-footer">
                    Belum punya akun? <a href="register.php">Daftar Sekarang</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>