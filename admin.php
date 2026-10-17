<?php
session_set_cookie_params(['lifetime'=>1800,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['level'] != 'admin') {
    echo "<script>alert('Hak akses ditolak! Hanya Admin yang boleh masuk.'); window.location='index.php';</script>";
    exit();
}
include 'koneksi.php';
include 'helper.php';

initSecureSession();

$user_id = $_SESSION['user_id'];
$msg = '';

// Tampilkan error dari session (untuk redirect setelah delete)
if (isset($_SESSION['delete_error'])) {
    $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>" . htmlspecialchars($_SESSION['delete_error']) . "</div>";
    unset($_SESSION['delete_error']);
}

// Hitung total bobot kriteria saat ini
$stmt_bobot_check = $conn->prepare("SELECT COALESCE(SUM(bobot), 0) as total_bobot FROM kriteria");
$stmt_bobot_check->execute();
$total_bobot_current = $stmt_bobot_check->get_result()->fetch_assoc()['total_bobot'];
$stmt_bobot_check->close();

$bobot_valid = abs($total_bobot_current - 1.0) <= 0.001;

if (isset($_POST['add_kriteria'])) {
    verifyCsrfToken();
    $kode = strtoupper(trim($_POST['kode']));
    $nama = trim($_POST['nama']);
    $bobot = floatval($_POST['bobot']);
    
    if (empty($kode) || empty($nama) || $bobot < 0) {
        $msg = "<div class='alert alert-warning'>Input tidak valid!</div>";
    } else {
        // Validasi total bobot harus = 1.0
        $stmt_check = $conn->prepare("SELECT COALESCE(SUM(bobot), 0) as total_bobot FROM kriteria");
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $current_total = $result_check->fetch_assoc()['total_bobot'];
        $stmt_check->close();
        
        $new_total = $current_total + $bobot;
        
        if (abs($new_total - 1.0) > 0.001) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i><strong>Validasi Bobot Gagal!</strong><br>";
            $msg .= "Total bobot kriteria harus sama dengan <strong>1.0</strong>.<br>";
            $msg .= "Bobot saat ini: <strong>" . number_format($current_total, 3) . "</strong><br>";
            $msg .= "Bobot yang akan ditambah: <strong>" . number_format($bobot, 3) . "</strong><br>";
            $msg .= "Total akan menjadi: <strong>" . number_format($new_total, 3) . "</strong> (harus 1.0)<br>";
            $msg .= "Silakan sesuaikan nilai bobot.</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO kriteria (kode, nama_kriteria, bobot) VALUES (?, ?, ?)");
            $stmt->bind_param("ssd", $kode, $nama, $bobot);
            if ($stmt->execute()) {
                logActivity($conn, $user_id, 'ADD_KRITERIA', 'Added criteria: ' . $kode);
                $msg = "<div class='alert alert-success'>Kriteria berhasil ditambahkan!</div>";
            } else {
                $msg = "<div class='alert alert-danger'>Kode kriteria sudah ada atau error!</div>";
            }
            $stmt->close();
        }
    }
}

if (isset($_POST['add_tema'])) {
    verifyCsrfToken();
    $nama_tema = trim($_POST['nama_tema']);
    
    if (empty($nama_tema)) {
        $msg = "<div class='alert alert-warning'>Nama tema tidak boleh kosong!</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO tema (nama_tema, id_user) VALUES (?, ?)");
        $stmt->bind_param("si", $nama_tema, $user_id);
        if ($stmt->execute()) {
            logActivity($conn, $user_id, 'ADD_TEMA', 'Added theme: ' . $nama_tema);
            $msg = "<div class='alert alert-success'>Tema berhasil ditambahkan!</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Gagal menambahkan tema!</div>";
        }
        $stmt->close();
    }
}

if (isset($_POST['del_k'])) {
    verifyCsrfToken();
    $id = intval($_POST['del_k']);
    
    // Ambil bobot kriteria yang akan dihapus
    $stmt_check = $conn->prepare("SELECT kode, bobot FROM kriteria WHERE id=?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $kriteria_data = $result->fetch_assoc();
    $stmt_check->close();
    
    if ($kriteria_data) {
        $bobot_dihapus = $kriteria_data['bobot'];
        
        // Hitung total bobot setelah penghapusan
        $stmt_total = $conn->prepare("SELECT COALESCE(SUM(bobot), 0) as total_bobot FROM kriteria");
        $stmt_total->execute();
        $current_total = $stmt_total->get_result()->fetch_assoc()['total_bobot'];
        $stmt_total->close();
        
        $new_total = $current_total - $bobot_dihapus;
        
        // Izinkan hapus jika total akan menjadi 0 (hapus semua) atau masih valid
        if ($new_total > 0 && abs($new_total - 1.0) > 0.001) {
            $_SESSION['delete_error'] = "Tidak dapat menghapus kriteria! Total bobot akan menjadi " . number_format($new_total, 3) . " (harus 1.0). Sesuaikan bobot kriteria lain terlebih dahulu.";
        } else {
            $stmt = $conn->prepare("DELETE FROM kriteria WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                logActivity($conn, $user_id, 'DELETE_KRITERIA', 'Deleted criteria ID: ' . $id);
            }
            $stmt->close();
        }
    }
    
    header("Location: admin.php");
    exit();
}

if (isset($_POST['del_t'])) {
    verifyCsrfToken();
    $id = intval($_POST['del_t']);
    $stmt = $conn->prepare("DELETE FROM tema WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        logActivity($conn, $user_id, 'DELETE_TEMA', 'Deleted theme ID: ' . $id);
    }
    $stmt->close();
    header("Location: admin.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM kriteria ORDER BY kode ASC");
$stmt->execute();
$q_kriteria = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM tema ORDER BY id DESC");
$stmt->execute();
$q_tema = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin SPK - Kelola Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .section-card {
            border-radius: 16px;
            animation: fadeInUp 0.6s ease-out;
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px;
            border-radius: 16px 16px 0 0;
        }

        .add-form {
            background: linear-gradient(135deg, #f0f4ff 0%, #f8f0ff 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            animation: fadeInDown 0.5s ease-out;
        }

        .item-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .item-list-item {
            padding: 14px 16px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            animation: fadeInUp 0.4s ease-out backwards;
        }

        .item-list-item:hover {
            background: linear-gradient(135deg, #f8f9ff 0%, #fff0ff 100%);
            transform: translateX(5px);
        }

        .item-list-item:last-child {
            border-bottom: none;
        }

        .item-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .item-meta {
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .delete-btn {
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .delete-btn:hover {
            transform: scale(1.1);
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
        <i class="fas fa-user-shield fa-3x mb-3 text-warning"></i>
        <h5>MENU ADMIN</h5>
        <small class="text-muted d-block">Administrator</small>
        <hr>
    </div>
    <nav>
        <a href="index.php"><i class="fas fa-chart-line me-2"></i> Dashboard</a>
        <a href="profil.php"><i class="fas fa-user-edit me-2"></i> Profil</a>
        <a href="admin.php" class="active"><i class="fas fa-database me-2"></i> Kelola Data Dasar</a>
        <a href="data_mahasiswa.php"><i class="fas fa-users me-2"></i> Data Mahasiswa</a>
        <a href="activity_log.php"><i class="fas fa-history me-2"></i> Activity Log</a>
        <a href="input_nilai.php"><i class="fas fa-edit me-2"></i> Input Nilai</a>
        <hr class="mx-3 opacity-25">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a>
    </nav>
</div>

<div class="main-content">
    <div class="mb-4">
        <h2 class="fw-bold mb-2"><i class="fas fa-cogs me-2 text-primary"></i>Kelola Data Dasar</h2>
        <p class="text-muted">Atur kriteria, bobot, dan daftar tema tugas akhir</p>
    </div>

    <?= $msg; ?>

    <?php if (!$bobot_valid && $total_bobot_current > 0): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-circle me-2"></i>
        <strong>Peringatan Bobot Kriteria:</strong> Total bobot saat ini <strong><?= number_format($total_bobot_current, 3) ?></strong> (harus 1.0). 
        Sistem akan tetap berjalan, namun hasil perhitungan mungkin tidak akurat. Silakan sesuaikan bobot kriteria.
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-7">
            <div class="section-card card">
                <div class="section-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="fas fa-list-check me-2"></i>1. Kriteria & Bobot</h5>
                        <span class="badge <?= $bobot_valid ? 'bg-success' : 'bg-danger' ?> px-3 py-2">
                            <i class="fas <?= $bobot_valid ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-1"></i>
                            Total: <?= number_format($total_bobot_current, 3) ?> / 1.0
                        </span>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="add-form">
                                            <form method="POST" class="row g-2">
                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                <div class="col-md-3">
                                <input type="text" name="kode" class="form-control" placeholder="Kode (C1, C2...)" required>
                            </div>
                            <div class="col-md-5">
                                <input type="text" name="nama" class="form-control" placeholder="Nama Kriteria" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="bobot" step="0.01" class="form-control" placeholder="Bobot" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="add_kriteria" class="btn btn-primary w-100">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="item-list">
                                            <?php while($k = $q_kriteria->fetch_assoc()): ?>
                                            <div class="item-list-item">
                                                <div>
                                                    <span class="badge bg-primary me-2"><?= htmlspecialchars($k['kode']); ?></span>
                                                    <span class="item-name"><?= htmlspecialchars($k['nama_kriteria']); ?></span>
                                                    <div class="item-meta">Bobot: <strong><?= htmlspecialchars($k['bobot']); ?></strong></div>
                                                </div>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus kriteria ini?')">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                    <input type="hidden" name="del_k" value="<?= (int)$k['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger delete-btn">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="section-card card">
                <div class="section-header">
                    <h5 class="fw-bold mb-0"><i class="fas fa-book me-2"></i>2. Daftar Tema TA</h5>
                </div>
                
                <div class="card-body p-0">
                    <div class="add-form">
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                <div class="input-group">
                                <input type="text" name="nama_tema" class="form-control" placeholder="Judul Tema Baru" required>
                                <button type="submit" name="add_tema" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Tambah
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="item-list">
                                            <?php while($t = $q_tema->fetch_assoc()): ?>
                                            <div class="item-list-item">
                                                <div>
                                                    <i class="fas fa-file-alt me-2 text-success"></i>
                                                    <span class="item-name"><?= htmlspecialchars($t['nama_tema']); ?></span>
                                                </div>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus tema ini?')">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                    <input type="hidden" name="del_t" value="<?= (int)$t['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger delete-btn">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/sidebar.js"></script>
</body>
</html>
