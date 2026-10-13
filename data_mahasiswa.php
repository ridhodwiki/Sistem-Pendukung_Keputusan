<?php
session_set_cookie_params(['lifetime'=>1800,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (!isset($_SESSION['username']) || $_SESSION['level'] != 'admin') {
    header("Location: index.php");
    exit();
}
include 'koneksi.php';
include 'helper.php';

initSecureSession();

$user_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$msg = '';

if (isset($_POST['tambah_mhs'])) {
    verifyCsrfToken();
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $nama = trim($_POST['nama_lengkap']);
    $npm = trim($_POST['npm']);
    $kls = trim($_POST['kelas']);
    
    if (empty($user) || empty($pass)) {
        $msg = "<div class='alert alert-warning'>Username dan password harus diisi!</div>";
    } else {
        $pass_hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, npm, kelas, level) VALUES (?, ?, ?, ?, ?, 'mahasiswa')");
        $stmt->bind_param("sssss", $user, $pass_hash, $nama, $npm, $kls);
        if ($stmt->execute()) {
            logActivity($conn, $user_id, 'ADD_MAHASISWA', 'Added student: ' . $user);
            $msg = "<div class='alert alert-success'>Mahasiswa berhasil ditambahkan!</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Username sudah terdaftar atau error!</div>";
        }
        $stmt->close();
    }
}

if (isset($_POST['delete_mhs'])) {
    verifyCsrfToken();
    $id = intval($_POST['delete_mhs']);
    $stmt = $conn->prepare("SELECT username FROM users WHERE id=? AND level='mahasiswa'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $del_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($del_user) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND level='mahasiswa'");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            logActivity($conn, $user_id, 'DELETE_MAHASISWA', 'Deleted student: ' . $del_user['username']);
        }
        $stmt->close();
    }
    header("Location: data_mahasiswa.php");
    exit();
}

if (!empty($search)) {
    $search_pattern = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT * FROM users WHERE level='mahasiswa' AND (nama_lengkap LIKE ? OR npm LIKE ? OR username LIKE ?) ORDER BY id DESC");
    $stmt->bind_param("sss", $search_pattern, $search_pattern, $search_pattern);
    $stmt->execute();
    $query = $stmt->get_result();
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE level='mahasiswa' ORDER BY id DESC");
    $stmt->execute();
    $query = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Mahasiswa - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .search-bar {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
            animation: fadeInDown 0.6s ease-out;
        }

        .search-bar .input-group {
            gap: 10px;
        }

        .search-bar .form-control {
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            padding: 12px 16px;
        }

        .table-row-animation {
            animation: fadeInUp 0.4s ease-out backwards;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 60px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        .stat-mini {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
        }
    </style>
</head>
<body>

<div class="sidebar shadow">
    <div class="p-4 text-center">
        <i class="fas fa-users-cog fa-3x mb-3 text-warning"></i>
        <h5>ADMIN PANEL</h5>
        <small class="text-muted d-block">Manajemen Pengguna</small>
        <hr>
    </div>
    <nav>
        <a href="index.php"><i class="fas fa-chart-line me-2"></i> Dashboard</a>
        <a href="profil.php"><i class="fas fa-user-edit me-2"></i> Profil</a>
        <a href="admin.php"><i class="fas fa-database me-2"></i> Kelola Data Dasar</a>
        <a href="data_mahasiswa.php" class="active"><i class="fas fa-users me-2"></i> Data Mahasiswa</a>
        <a href="activity_log.php"><i class="fas fa-history me-2"></i> Activity Log</a>
        <a href="input_nilai.php"><i class="fas fa-edit me-2"></i> Input Nilai</a>
        <hr class="mx-3 opacity-25">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-users me-2 text-primary"></i>Manajemen Data Mahasiswa</h2>
            <p class="text-muted small">Kelola daftar mahasiswa dan lihat hasil penilaian mereka</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-user-plus me-2"></i>Tambah Mahasiswa
        </button>
    </div>

    <?= $msg; ?>

    <div class="search-bar">
        <form method="GET" class="row g-2">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text" style="border: none; background: white;">
                        <i class="fas fa-search text-primary"></i>
                    </span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama, NPM, atau username..." value="<?= htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i>Cari
                </button>
            </div>
            <?php if (!empty($search)): ?>
                <div class="col-md-3">
                    <a href="data_mahasiswa.php" class="btn btn-secondary w-100">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-header bg-white border-bottom-0 p-4">
            <h5 class="fw-bold mb-0">
                <i class="fas fa-list me-2 text-primary"></i>Daftar Mahasiswa
                <span class="stat-mini" style="animation: pulse 2s infinite;">
                    Total: <span id="totalMhs">0</span>
                </span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%;" class="text-center">#</th>
                        <th style="width: 35%;">👤 Identitas Mahasiswa</th>
                        <th style="width: 35%;">🏆 Rekomendasi Terbaik</th>
                        <th style="width: 15%;" class="text-center">⭐ Skor</th>
                        <th style="width: 10%;" class="text-center">⚙️ Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $totalCount = 0;
                    while($row = $query->fetch_assoc()): 
                        $totalCount++;
                        $id_mhs = $row['id'];
                        $stmt = $conn->prepare("SELECT t.nama_tema, 
                            ((n.c1 * (SELECT COALESCE(MAX(CASE WHEN kode='C1' THEN bobot END), 0) FROM kriteria)) + 
                             (n.c2 * (SELECT COALESCE(MAX(CASE WHEN kode='C2' THEN bobot END), 0) FROM kriteria)) + 
                             (n.c3 * (SELECT COALESCE(MAX(CASE WHEN kode='C3' THEN bobot END), 0) FROM kriteria)) + 
                             (n.c4 * (SELECT COALESCE(MAX(CASE WHEN kode='C4' THEN bobot END), 0) FROM kriteria)) + 
                             (n.c5 * (SELECT COALESCE(MAX(CASE WHEN kode='C5' THEN bobot END), 0) FROM kriteria))) as total 
                            FROM penilaian n 
                            JOIN tema t ON n.id_tema = t.id 
                            WHERE t.id_user = ? 
                            ORDER BY total DESC LIMIT 1");
                        $stmt->bind_param("i", $id_mhs);
                        $stmt->execute();
                        $top_rank = $stmt->get_result();
                        $data_rank = $top_rank->fetch_assoc();
                        $stmt->close();
                    ?>
                    <tr class="table-row-animation" style="animation-delay: <?= (0.1 + $no * 0.05) ?>s;">
                        <td class="text-center fw-bold text-primary"><?= $no ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($row['nama_lengkap'] ?: $row['username']); ?></div>
                            <small class="text-muted">
                                <i class="fas fa-id-card me-1"></i><?= htmlspecialchars($row['npm']); ?> 
                                <span class="ms-2"><i class="fas fa-graduation-cap me-1"></i>Kelas <?= htmlspecialchars($row['kelas']); ?></span>
                            </small>
                        </td>
                        <td>
                            <?php if($data_rank): ?>
                                <span class="badge bg-success px-3 py-2" style="animation: pulse 2s infinite;">
                                    <i class="fas fa-trophy me-1"></i><?= htmlspecialchars($data_rank['nama_tema']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary px-3 py-2">
                                    <i class="fas fa-hourglass-half me-1"></i>Belum Ada
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary px-3 py-2" style="font-size: 0.95rem;">
                                <?= $data_rank ? number_format($data_rank['total'], 3) : '0.000' ?>
                            </span>
                        </td>
                        <td class="text-center">
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus data mahasiswa ini?')">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                        <input type="hidden" name="delete_mhs" value="<?= (int)$row['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                    </tr>
                    <?php $no++; endwhile; ?>
                </tbody>
            </table>
            
            <?php if($totalCount == 0): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5 class="text-muted">Tidak ada data mahasiswa</h5>
                    <p class="text-muted small">Mulai dengan menambahkan mahasiswa baru</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>Tambah Mahasiswa Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <div class="mb-3">
                    <label class="form-label fw-bold">Username Login</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="username" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="password" required>
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama lengkap">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">NPM</label>
                    <input type="text" name="npm" class="form-control" placeholder="NPM">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Kelas</label>
                    <input type="text" name="kelas" class="form-control" placeholder="Kelas (Contoh: 4A)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="tambah_mhs" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Simpan Mahasiswa
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('totalMhs').textContent = document.querySelectorAll('tbody tr:not(.d-none)').length;
</script>
</body>
</html>
