<?php
session_set_cookie_params(['lifetime'=>1800,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
session_start();
include 'koneksi.php';
include 'helper.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

initSecureSession();

$user_id = $_SESSION['user_id'];
$msg = "";

$stmt = $conn->prepare("SELECT id, username, level, nama_lengkap, npm, kelas FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$get_user = $stmt->get_result();
$d = $get_user->fetch_assoc();
$stmt->close();

if (!$d) {
    $d = [
        'id' => $user_id,
        'username' => $_SESSION['username'],
        'level' => $_SESSION['level'],
        'nama_lengkap' => '',
        'npm' => '',
        'kelas' => ''
    ];
}

if (isset($_POST['update_profil'])) {
    verifyCsrfToken();
    $nama = trim($_POST['nama_lengkap']);
    $npm = trim($_POST['npm']);
    $kls = trim($_POST['kelas']);
    
    if (empty($nama)) {
        $msg = "<div class='alert alert-warning mt-2'>Nama lengkap tidak boleh kosong!</div>";
    } else {
        $stmt = $conn->prepare("UPDATE users SET nama_lengkap=?, npm=?, kelas=? WHERE id=?");
        $stmt->bind_param("sssi", $nama, $npm, $kls, $user_id);
        if ($stmt->execute()) {
            logActivity($conn, $user_id, 'UPDATE_PROFIL', 'Updated profile information');
            $msg = "<div class='alert alert-success mt-2'><i class='fas fa-check-circle me-2'></i>Profil berhasil diperbarui!</div>";
            $d['nama_lengkap'] = $nama;
            $d['npm'] = $npm;
            $d['kelas'] = $kls;
        } else {
            $msg = "<div class='alert alert-danger mt-2'>Gagal memperbarui profil.</div>";
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
    <title>Profil - SPK WSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInDown 0.6s ease-out;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .profile-header i {
            font-size: 60px;
            margin-bottom: 15px;
            z-index: 1;
            position: relative;
            animation: bounce 2s infinite;
        }

        .profile-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
            animation: fadeInUp 0.6s ease-out;
        }

        .profile-section h5 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f4ff;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .form-group {
            animation: fadeInUp 0.5s ease-out backwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }

        .info-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 10px;
            margin-bottom: 10px;
            animation: pulse 2s infinite;
        }

        .readonly-field {
            background: linear-gradient(135deg, #f0f4ff 0%, #f8f0ff 100%);
            border: 2px solid #e0e8ff !important;
        }
    </style>
</head>
<body>

<div class="mobile-topbar">
    <button class="btn btn-primary sidebar-toggle" aria-label="Buka menu navigasi" aria-expanded="false"><i class="fas fa-bars"></i></button>
    <span class="brand">SPK TEMA TA</span>
</div>
<div class="sidebar-overlay"></div>

<div class="sidebar shadow">
    <div class="p-4 text-center">
        <i class="fas fa-user-circle fa-3x mb-3 text-warning"></i>
        <h5><?= strtoupper($_SESSION['level']); ?> PANEL</h5>
        <small class="text-muted d-block">User Profile</small>
        <hr>
    </div>
    <nav>
        <a href="index.php"><i class="fas fa-chart-line me-2"></i> Dashboard</a>
        <a href="profil.php" class="active">
            <i class="fas fa-user-edit me-2"></i> 
            <?= ($_SESSION['level'] == 'admin') ? 'Profil Mahasiswa' : 'Profil Saya'; ?>
        </a>
        <?php if($_SESSION['level'] == 'admin'): ?>
            <a href="admin.php"><i class="fas fa-database me-2"></i> Kelola Data Dasar</a>
            <a href="data_mahasiswa.php"><i class="fas fa-users me-2"></i> Data Mahasiswa</a>
            <a href="activity_log.php"><i class="fas fa-history me-2"></i> Activity Log</a>
        <?php endif; ?>
        <a href="input_nilai.php"><i class="fas fa-edit me-2"></i> Input Nilai</a>
        <hr class="mx-3 opacity-25">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a>
    </nav>
</div>

<div class="main-content">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="profile-header">
                <i class="fas fa-user-check"></i>
                <h2 class="mb-2 fw-bold">Pengaturan Profil Anda</h2>
                <p class="mb-0 opacity-90">Kelola informasi pribadi dan akun Anda</p>
            </div>

            <div class="profile-section">
                <h5><i class="fas fa-shield-alt me-2"></i>Informasi Akun</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold">Username (ID Login)</label>
                        <input type="text" class="form-control readonly-field bg-light" value="<?= htmlspecialchars($d['username']); ?>" readonly>
                        <small class="text-muted d-block mt-2"><i class="fas fa-lock me-1"></i>Username tidak dapat diubah</small>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold">Level Akses</label>
                        <input type="text" class="form-control readonly-field bg-light" value="<?= ucfirst(htmlspecialchars($d['level'])); ?>" readonly>
                        <small class="text-muted d-block mt-2">
                            <span class="info-badge">
                                <i class="fas fa-badge-check me-1"></i><?= $d['level'] == 'admin' ? 'Administrator' : 'Mahasiswa'; ?>
                            </span>
                        </small>
                    </div>
                </div>
            </div>

            <?= $msg; ?>

            <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <div class="profile-section">
                    <h5><i class="fas fa-user me-2"></i>Data Pribadi</h5>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label fw-bold">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" 
                                   value="<?= htmlspecialchars($d['nama_lengkap']); ?>" 
                                   placeholder="Masukkan nama asli Anda" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label fw-bold">NPM / NIM</label>
                            <input type="text" name="npm" class="form-control" 
                                   value="<?= htmlspecialchars($d['npm']); ?>" 
                                   placeholder="Contoh: 2467..." required>
                        </div>

                        <div class="form-group">
                            <label class="form-label fw-bold">Kelas</label>
                            <input type="text" name="kelas" class="form-control" 
                                   value="<?= htmlspecialchars($d['kelas']); ?>" 
                                   placeholder="Contoh: 4A" required>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="update_profil" class="btn btn-primary px-5 py-2">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                    <a href="index.php" class="btn btn-secondary px-5 py-2">
                        <i class="fas fa-times me-2"></i>Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/sidebar.js"></script>
</body>
</html>
