<?php
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
$filter_action = isset($_GET['action']) ? trim($_GET['action']) : '';

$query = "SELECT la.*, u.username FROM log_aktivitas la LEFT JOIN users u ON la.user_id = u.id WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $query .= " AND (u.username LIKE ? OR la.action LIKE ? OR la.details LIKE ?)";
    $params = [$search_term, $search_term, $search_term];
    $types = 'sss';
}

if (!empty($filter_action)) {
    $query .= " AND la.action = ?";
    $params[] = $filter_action;
    $types .= 's';
}

$query .= " ORDER BY la.created_at DESC LIMIT 500";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $q_logs = $stmt->get_result();
} else {
    $q_logs = $conn->query($query);
}

$actions = ['LOGIN', 'LOGIN_FAILED', 'LOGOUT', 'ADD_KRITERIA', 'DELETE_KRITERIA', 'ADD_TEMA', 'DELETE_TEMA', 'ADD_MAHASISWA', 'DELETE_MAHASISWA', 'UPDATE_PROFIL', 'UPDATE_NILAI', 'CREATE_NILAI'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Admin SPK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
            animation: fadeInDown 0.6s ease-out;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .activity-log-table {
            border-radius: 16px;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }

        .log-row {
            animation: fadeInUp 0.4s ease-out backwards;
            border-bottom: 1px solid #e9ecef;
        }

        .log-row:hover {
            background: linear-gradient(135deg, #f8f9ff 0%, #fff0ff 100%);
        }

        .log-action-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-login { background: linear-gradient(135deg, var(--success) 0%, #00b896 100%); color: white; }
        .badge-login-failed { background: linear-gradient(135deg, var(--danger) 0%, #ff5252 100%); color: white; }
        .badge-add { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; }
        .badge-delete { background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%); color: white; }
        .badge-update { background: linear-gradient(135deg, var(--warning) 0%, #ff8c00 100%); color: white; }
        .badge-other { background: linear-gradient(135deg, #a8a8a8 0%, #7f8c8d 100%); color: white; }

        .timestamp {
            font-size: 0.85rem;
            color: #7f8c8d;
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

        .stat-info {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>

<div class="sidebar shadow">
    <div class="p-4 text-center">
        <i class="fas fa-history fa-3x mb-3 text-warning"></i>
        <h5>ACTIVITY LOG</h5>
        <small class="text-muted d-block">Riwayat Aktivitas</small>
        <hr>
    </div>
    <nav>
        <a href="index.php"><i class="fas fa-chart-line me-2"></i> Dashboard</a>
        <a href="profil.php"><i class="fas fa-user-edit me-2"></i> Profil</a>
        <a href="admin.php"><i class="fas fa-database me-2"></i> Kelola Data Dasar</a>
        <a href="data_mahasiswa.php"><i class="fas fa-users me-2"></i> Data Mahasiswa</a>
        <a href="activity_log.php" class="active"><i class="fas fa-history me-2"></i> Activity Log</a>
        <a href="input_nilai.php"><i class="fas fa-edit me-2"></i> Input Nilai</a>
        <hr class="mx-3 opacity-25">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-history me-2 text-primary"></i>Riwayat Aktivitas Sistem</h2>
            <p class="text-muted small">Pantau semua aktivitas pengguna dalam sistem SPK WSM</p>
        </div>
    </div>

    <div class="filter-section">
        <form method="GET" class="filter-row">
            <div>
                <label class="form-label small fw-bold mb-2">Cari Pengguna/Aksi</label>
                <input type="text" name="search" class="form-control" placeholder="Username, action, atau detail..." value="<?= htmlspecialchars($search); ?>">
            </div>
            <div>
                <label class="form-label small fw-bold mb-2">Filter Aksi</label>
                <select name="action" class="form-select">
                    <option value="">-- Semua Aksi --</option>
                    <?php foreach($actions as $act): ?>
                        <option value="<?= $act ?>" <?= $filter_action === $act ? 'selected' : '' ?>><?= $act ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
                <?php if (!empty($search) || !empty($filter_action)): ?>
                    <a href="activity_log.php" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card activity-log-table">
        <div class="card-header bg-white border-bottom-0 p-4">
            <h5 class="fw-bold mb-0">
                <i class="fas fa-list me-2 text-primary"></i>Daftar Aktivitas
                <span class="stat-info" style="animation: pulse 2s infinite;">
                    Total: <span id="totalLogs">0</span>
                </span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 8%;" class="text-center">#</th>
                        <th style="width: 15%;">👤 Pengguna</th>
                        <th style="width: 18%;">⚡ Aksi</th>
                        <th style="width: 35%;">📝 Detail</th>
                        <th style="width: 24%;" class="text-end">🕐 Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    $totalCount = 0;
                    while($row = $q_logs->fetch_assoc()):
                        $totalCount++;
                        $action = $row['action'];
                        if (strpos($action, 'LOGIN') !== false && strpos($action, 'FAILED') !== false) {
                            $badge_class = 'badge-login-failed';
                        } elseif (strpos($action, 'LOGIN') !== false) {
                            $badge_class = 'badge-login';
                        } elseif (strpos($action, 'ADD') !== false || strpos($action, 'CREATE') !== false) {
                            $badge_class = 'badge-add';
                        } elseif (strpos($action, 'DELETE') !== false) {
                            $badge_class = 'badge-delete';
                        } elseif (strpos($action, 'UPDATE') !== false) {
                            $badge_class = 'badge-update';
                        } else {
                            $badge_class = 'badge-other';
                        }
                    ?>
                    <tr class="log-row" style="animation-delay: <?= (0.1 + $no * 0.03) ?>s;">
                        <td class="text-center fw-bold text-primary"><?= $no ?></td>
                        <td>
                            <div class="fw-semibold">
                                <i class="fas fa-user-circle me-1" style="color: var(--primary);"></i>
                                <?= htmlspecialchars($row['username'] ?: 'System'); ?>
                            </div>
                        </td>
                        <td>
                            <span class="log-action-badge <?= $badge_class ?>">
                                <?= htmlspecialchars($action); ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?= htmlspecialchars($row['details'] ?: '—'); ?>
                            </small>
                        </td>
                        <td class="text-end timestamp">
                            <i class="fas fa-clock me-1"></i>
                            <?= date('d/m/Y H:i:s', strtotime($row['created_at'])); ?>
                        </td>
                    </tr>
                    <?php $no++; endwhile; ?>
                </tbody>
            </table>

            <?php if($totalCount == 0): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5 class="text-muted">Tidak ada aktivitas ditemukan</h5>
                    <p class="text-muted small">Coba ubah filter pencarian Anda</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('totalLogs').textContent = document.querySelectorAll('tbody tr').length;
</script>
</body>
</html>
