<?php
session_start();
include 'koneksi.php';
include 'helper.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

initSecureSession();

$me = currentUserIdentity($conn);
if (!$me) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user_id = (int) $me['id'];
$level = $me['level'];
$is_admin = ($level === 'admin');

ensureRatingOwnershipSchema($conn);

$stmt = $conn->prepare("SELECT * FROM kriteria ORDER BY kode ASC");
$stmt->execute();
$q_kriteria = $stmt->get_result();
$bobot_kriteria = [];
while ($k = $q_kriteria->fetch_assoc()) {
    $bobot_kriteria[strtoupper($k['kode'])] = $k['bobot'];
}

$hasil_wsm = [];

if ($is_admin) {
    // Admin: ranking GLOBAL semua mahasiswa (hanya admin yang bisa akses)
    $stmt = $conn->prepare("SELECT n.id, n.id_tema, n.c1, n.c2, n.c3, n.c4, n.c5, t.nama_tema, t.id_user, u.nama_lengkap 
                           FROM penilaian n 
                           JOIN tema t ON n.id_tema = t.id
                           LEFT JOIN users u ON t.id_user = u.id 
                           ORDER BY n.id DESC");
    $stmt->execute();
} else {
    // Mahasiswa: HANYA penilaian milik sendiri (isolasi penuh)
    $stmt = $conn->prepare("SELECT n.id, n.id_tema, n.c1, n.c2, n.c3, n.c4, n.c5, t.nama_tema, t.id_user, u.nama_lengkap
                            FROM penilaian n
                            JOIN tema t ON n.id_tema = t.id
                            LEFT JOIN users u ON t.id_user = u.id
                            WHERE n.user_id = ?
                            ORDER BY n.id DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

$q_penilaian = $stmt->get_result();

// DEBUG: Cek data penilaian
$debug_count = $q_penilaian->num_rows;
// echo "<!-- DEBUG: Total penilaian rows = $debug_count -->";

while ($row = $q_penilaian->fetch_assoc()) {
    $skor_akhir = ($row['c1'] * ($bobot_kriteria['C1'] ?? 0)) +
                  ($row['c2'] * ($bobot_kriteria['C2'] ?? 0)) +
                  ($row['c3'] * ($bobot_kriteria['C3'] ?? 0)) +
                  ($row['c4'] * ($bobot_kriteria['C4'] ?? 0)) +
                  ($row['c5'] * ($bobot_kriteria['C5'] ?? 0));
    
    $hasil_wsm[] = [
        'tema' => $row['nama_tema'], 
        'skor' => $skor_akhir,
        'user_name' => $row['nama_lengkap'] ?? $row['id_user']
    ];
}

if (!empty($hasil_wsm)) {
    usort($hasil_wsm, function($a, $b) { return $b['skor'] <=> $a['skor']; });
}

// DEBUG: Tampilkan info
// echo "<!-- DEBUG: Total hasil_wsm = " . count($hasil_wsm) . " -->";
// echo "<!-- DEBUG: Total bobot C1=" . ($bobot_kriteria['C1'] ?? 'NULL') . " C2=" . ($bobot_kriteria['C2'] ?? 'NULL') . " -->";
// if (!empty($hasil_wsm)) {
//     echo "<!-- DEBUG: Top tema = " . $hasil_wsm[0]['tema'] . " | skor = " . $hasil_wsm[0]['skor'] . " -->";
// }

if ($is_admin) {
    $stmt_users = $conn->prepare("SELECT COUNT(id) as total FROM users WHERE level='mahasiswa'");
    $stmt_users->execute();
    $total_users = $stmt_users->get_result()->fetch_assoc()['total'];
    $stmt_users->close();

    $stmt_tema = $conn->prepare("SELECT COUNT(id) as total FROM tema");
    $stmt_tema->execute();
    $total_tema = $stmt_tema->get_result()->fetch_assoc()['total'];
    $stmt_tema->close();

    $stmt_penilaian = $conn->prepare("SELECT COUNT(id) as total FROM penilaian");
    $stmt_penilaian->execute();
    $total_penilaian = $stmt_penilaian->get_result()->fetch_assoc()['total'];
    $stmt_penilaian->close();
} else {
    $stmt_tema = $conn->prepare("SELECT COUNT(id) as total FROM tema WHERE id_user = ?");
    $stmt_tema->bind_param("i", $user_id);
    $stmt_tema->execute();
    $total_tema = $stmt_tema->get_result()->fetch_assoc()['total'];
    $stmt_tema->close();

    $stmt_penilaian = $conn->prepare("SELECT COUNT(id) as total FROM penilaian WHERE user_id = ?");
    $stmt_penilaian->bind_param("i", $user_id);
    $stmt_penilaian->execute();
    $total_penilaian = $stmt_penilaian->get_result()->fetch_assoc()['total'];
    $stmt_penilaian->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPK WSM - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="mobile-topbar">
    <button class="btn btn-primary sidebar-toggle" aria-label="Buka menu navigasi" aria-expanded="false"><i class="fas fa-bars"></i></button>
    <span class="brand">SPK TEMA TA</span>
</div>
<div class="sidebar-overlay"></div>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 p-0 sidebar shadow">
            <div class="p-4 text-center">
                <i class="fas fa-graduation-cap fa-3x mb-3 text-warning"></i>
                <h5>SPK TEMA TA</h5>
                <small class="text-muted d-block">Decision Support System</small>
                <hr>
            </div>
            <nav>
                <a href="index.php" class="active"><i class="fas fa-chart-line me-2"></i> Dashboard</a>
                <a href="profil.php"><i class="fas fa-user-edit me-2"></i> <?= ($level == 'admin') ? 'Profil Mahasiswa' : 'Profil Saya'; ?></a>
                <?php if($level == 'admin'): ?>
                    <a href="admin.php"><i class="fas fa-database me-2"></i> Kelola Data Dasar</a>
                    <a href="data_mahasiswa.php"><i class="fas fa-users me-2"></i> Data Mahasiswa</a>
                    <a href="activity_log.php"><i class="fas fa-history me-2"></i> Activity Log</a>
                <?php endif; ?>
                <a href="input_nilai.php"><i class="fas fa-edit me-2"></i> Input Nilai</a>
                <hr class="mx-3 opacity-25">
                <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a>
            </nav>
        </div>

        <div class="col-md-9 main-content">
            <div class="mb-4">
                <div class="card gradient-primary shadow-lg p-5" style="border-radius: 20px; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                    <div style="position: absolute; bottom: -30px; left: -30px; width: 150px; height: 150px; background: rgba(255,255,255,0.08); border-radius: 50%;"></div>
                    <div class="row align-items-center position-relative" style="z-index: 1;">
                        <div class="col-md-8">
                            <h1 class="display-5 fw-bold mb-2">Halo, <?= htmlspecialchars($_SESSION['username']); ?> 👋</h1>
                            <p class="lead mb-3" style="opacity: 0.9;">Sistem Pendukung Keputusan membantu menentukan prioritas tema Tugas Akhir menggunakan metode <strong>Weighted Sum Model (WSM)</strong>.</p>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge bg-light text-dark px-3 py-2"><i class="fas fa-check-circle me-2"></i>Status: Online</span>
                                <span class="badge bg-light text-dark px-3 py-2"><i class="fas fa-shield-alt me-2"></i>Akses: <?= ucfirst(htmlspecialchars($level)); ?></span>
                                <span class="badge bg-light text-dark px-3 py-2"><i class="fas fa-clock me-2"></i>Waktu: <span id="jam"></span></span>
                            </div>
                        </div>
                        <div class="col-md-4 text-end d-none d-md-block">
                            <i class="fas fa-chart-line" style="font-size: 80px; opacity: 0.2;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <?php if ($is_admin): ?>
                    <div class="col-md-4">
                        <div class="stat-card primary">
                            <i class="fas fa-book"></i>
                            <h6>Total Tema</h6>
                            <h3><?= $total_tema ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card success">
                            <i class="fas fa-users"></i>
                            <h6>Total Pengguna</h6>
                            <h3><?= $total_users ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card warning">
                            <i class="fas fa-chart-bar"></i>
                            <h6>Total Penilaian</h6>
                            <h3><?= $total_penilaian ?></h3>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-md-4">
                        <div class="stat-card primary">
                            <i class="fas fa-book"></i>
                            <h6>Tema Saya</h6>
                            <h3><?= $total_tema ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card success">
                            <i class="fas fa-edit"></i>
                            <h6>Penilaian Saya</h6>
                            <h3><?= $total_penilaian ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card warning">
                            <i class="fas fa-star"></i>
                            <h6>Ranking Teratas</h6>
                            <h3><?= !empty($hasil_wsm) ? '✓' : '—' ?></h3>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card p-4">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="card-title fw-bold mb-1"><i class="fas fa-trophy me-2 text-warning"></i>Rekomendasi Tema Terbaik</h5>
                                <p class="text-muted small mb-0">
                                    <?php if ($is_admin): ?>
                                        Tema dengan skor WSM tertinggi (global)
                                    <?php else: ?>
                                        Tema terbaik berdasarkan penilaian Anda
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-warning text-dark px-3 py-2">Rank #1</span>
                            </div>
                        </div>
                        <hr>
                        <div class="display-6 text-primary fw-bold" style="color: var(--primary);">
                            <?= $hasil_wsm[0]['tema'] ?? '—' ?>
                        </div>
                        <?php if(!empty($hasil_wsm)): ?>
                            <p class="text-muted mb-0">Skor: <span class="badge bg-success"><?= number_format($hasil_wsm[0]['skor'], 3) ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="header-title"><h4 class="fw-bold text-dark">📊 Analisis Ranking WSM</h4></div>
            
            <div class="mb-3 d-flex gap-2 flex-wrap">
                <a href="export.php?type=ranking" class="btn btn-success btn-sm" target="_blank">
                    <i class="fas fa-download me-1"></i>Export Ranking CSV
                </a>
                <?php if($level == 'admin'): ?>
                    <a href="export.php?type=mahasiswa" class="btn btn-info btn-sm" target="_blank">
                        <i class="fas fa-download me-1"></i>Export Mahasiswa CSV
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print Report
                </button>
            </div>

            <div class="card p-4 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 8%;" class="text-center"><i class="fas fa-medal"></i></th>
                                <th style="width: 50%;">Judul Tema</th>
                                <th style="width: 15%;" class="text-center">Skor WSM</th>
                                <th style="width: 27%;" class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($hasil_wsm)): ?>
                                <?php $no=1; foreach($hasil_wsm as $h): ?>
                                <tr class="<?= ($no == 1) ? 'rank-gold' : '' ?>" style="animation: fadeInUp <?= (0.5 + $no*0.1) ?>s ease-out;">
                                    <td class="text-center fw-bold">
                                        <?php if($no == 1): ?>
                                            <i class="fas fa-medal text-warning fa-lg" style="animation: bounce 2s infinite;"></i>
                                        <?php elseif($no == 2): ?>
                                            <i class="fas fa-medal text-secondary fa-lg"></i>
                                        <?php elseif($no == 3): ?>
                                            <i class="fas fa-medal" style="color: #cd7f32; font-size: 1.2rem;"></i>
                                        <?php else: ?>
                                            <span class="badge bg-dark"><?= $no ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold"><?= htmlspecialchars($h['tema']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary px-3 py-2" style="font-size: 0.9rem;">
                                            <?= number_format($h['skor'], 3) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if($no == 1): ?>
                                            <span class="badge bg-success px-3 py-2" style="animation: pulse 2s infinite;">
                                                <i class="fas fa-check-circle me-1"></i>Sangat Direkomendasikan
                                            </span>
                                        <?php elseif($no <= 3): ?>
                                            <span class="badge bg-info px-3 py-2">
                                                <i class="fas fa-star me-1"></i>Direkomendasikan
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark px-3 py-2 border">
                                                <i class="fas fa-ellipsis-h me-1"></i>Dipertimbangkan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php $no++; endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3" style="display: block;"></i>
                                        <p class="text-muted">Belum ada data penilaian di database.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/sidebar.js"></script>
<script>
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('id-ID');
        document.getElementById('jam').textContent = timeString;
    }
    setInterval(updateTime, 1000);
    updateTime();
</script>
</body>
</html>
