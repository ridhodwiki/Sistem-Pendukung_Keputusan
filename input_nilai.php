<?php
session_set_cookie_params(['lifetime'=>1800,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
include 'koneksi.php';
include 'helper.php';

initSecureSession();

$me = currentUserIdentity($conn);
if (!$me) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user_id = (int) $me['id'];
$is_admin = ($me['level'] === 'admin');

ensureRatingOwnershipSchema($conn);

$msg = '';

if (isset($_POST['simpan_nilai'])) {
    verifyCsrfToken();
    $id_tema = intval($_POST['id_tema']);
    $c1 = clampScore(floatval($_POST['c1'] ?? 1));
        $c2 = clampScore(floatval($_POST['c2'] ?? 1));
        $c3 = clampScore(floatval($_POST['c3'] ?? 1));
        $c4 = clampScore(floatval($_POST['c4'] ?? 1));
        $c5 = clampScore(floatval($_POST['c5'] ?? 1));

    if ($id_tema <= 0) {
        die("<script>alert('Pilih tema terlebih dahulu!'); history.back();</script>");
    }

    // Tema harus ada; mahasiswa boleh menilai tema miliknya ATAU tema master dari admin (id_user = 0 atau NULL atau admin)
    if ($is_admin) {
        $stmt_tema = $conn->prepare("SELECT id FROM tema WHERE id = ?");
        $stmt_tema->bind_param("i", $id_tema);
    } else {
        $stmt_tema = $conn->prepare("SELECT id FROM tema WHERE id = ? AND (id_user = ? OR id_user = 0 OR id_user IS NULL OR id_user IN (SELECT id FROM users WHERE level='admin'))");
        $stmt_tema->bind_param("ii", $id_tema, $user_id);
    }
    $stmt_tema->execute();
    $tema_exist = $stmt_tema->get_result()->num_rows;
    $stmt_tema->close();

    if ($tema_exist === 0) {
        die("<script>alert('Tema tidak ditemukan atau bukan milik Anda!'); history.back();</script>");
    }

    // Satu penilaian per (tema, user) - mengikuti UNIQUE KEY unique_user_tema
    $stmt = $conn->prepare("SELECT id FROM penilaian WHERE id_tema = ? AND user_id = ?");
    $stmt->bind_param("ii", $id_tema, $user_id);
    $stmt->execute();
    $sudah_ada = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($sudah_ada) {
        $stmt = $conn->prepare("UPDATE penilaian SET c1=?, c2=?, c3=?, c4=?, c5=? WHERE id_tema=? AND user_id=?");
        $stmt->bind_param("dddddii", $c1, $c2, $c3, $c4, $c5, $id_tema, $user_id);
        $aksi = 'UPDATE_NILAI';
        $pesan = 'Nilai berhasil diperbarui!';
    } else {
        $stmt = $conn->prepare("INSERT INTO penilaian (id_tema, user_id, c1, c2, c3, c4, c5) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiddddd", $id_tema, $user_id, $c1, $c2, $c3, $c4, $c5);
        $aksi = 'CREATE_NILAI';
        $pesan = 'Nilai berhasil disimpan!';
    }

    if ($stmt->execute()) {
        logActivity($conn, $user_id, $aksi, 'Scores for tema ID: ' . $id_tema);
        $msg = "<div class='alert alert-success mt-2'><i class='fas fa-check-circle me-2'></i>" . $pesan . "</div>";
    } else {
        $msg = "<div class='alert alert-danger mt-2'>Gagal menyimpan nilai.</div>";
    }
    $stmt->close();
}

// Filter tema: admin lihat semua, mahasiswa melihat tema miliknya DAN tema master admin (id_user = 0 / NULL)
if ($is_admin) {
    $stmt = $conn->prepare("SELECT id, nama_tema FROM tema ORDER BY nama_tema ASC");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT id, nama_tema FROM tema WHERE id_user = ? OR id_user = 0 OR id_user IS NULL OR id_user IN (SELECT id FROM users WHERE level='admin') ORDER BY nama_tema ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}
$q_tema = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM kriteria ORDER BY kode ASC");
$stmt->execute();
$q_kriteria = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SPK WSM - Input Nilai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .score-input {
            position: relative;
        }

        .score-input input {
            text-align: center;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .score-display {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2rem;
            opacity: 0.3;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .score-input input:focus ~ .score-display {
            opacity: 1;
            transform: translateY(-50%) scale(1.2);
        }

        .criteria-card {
            border: 2px solid #e0e0e0;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
            animation: fadeInUp 0.5s ease-out backwards;
        }

        .criteria-card:nth-child(1) { animation-delay: 0.1s; }
        .criteria-card:nth-child(2) { animation-delay: 0.2s; }
        .criteria-card:nth-child(3) { animation-delay: 0.3s; }
        .criteria-card:nth-child(4) { animation-delay: 0.4s; }
        .criteria-card:nth-child(5) { animation-delay: 0.5s; }

        .criteria-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
            transform: translateY(-5px);
        }

        .criteria-label {
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 12px;
            display: block;
        }

        .criteria-code {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-right: 8px;
        }

        .score-slider {
            appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: linear-gradient(90deg, #ff6b6b, #ffa500, #00d4aa);
            outline: none;
            margin: 20px 0;
        }

        .score-slider::-webkit-slider-thumb {
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            transition: all 0.2s ease;
        }

        .score-slider::-webkit-slider-thumb:hover {
            transform: scale(1.3);
        }

        .score-slider::-moz-range-thumb {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            cursor: pointer;
            border: none;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .score-value {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            padding: 8px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(240, 147, 251, 0.1) 100%);
            border-radius: 12px;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="sidebar shadow">
    <div class="p-4 text-center">
        <i class="fas fa-edit fa-3x mb-3 text-warning"></i>
        <h5>INPUT NILAI</h5>
        <small class="text-muted d-block">Penilaian Tema</small>
        <hr>
    </div>
    <nav>
        <a href="index.php"><i class="fas fa-chart-line me-2"></i> Dashboard</a>
        <a href="profil.php"><i class="fas fa-user-edit me-2"></i> <?= $is_admin ? 'Profil Mahasiswa' : 'Profil Saya'; ?></a>
        <?php if($is_admin): ?>
            <a href="admin.php"><i class="fas fa-database me-2"></i> Kelola Data Dasar</a>
            <a href="data_mahasiswa.php"><i class="fas fa-users me-2"></i> Data Mahasiswa</a>
            <a href="activity_log.php"><i class="fas fa-history me-2"></i> Activity Log</a>
        <?php endif; ?>
        <a href="input_nilai.php" class="active"><i class="fas fa-edit me-2"></i> Input Nilai</a>
        <hr class="mx-3 opacity-25">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a>
    </nav>
</div>

<div class="main-content">
    <div class="mb-4">
        <h2 class="fw-bold mb-2"><i class="fas fa-calculator me-2 text-primary"></i>Input Nilai Alternatif</h2>
        <p class="text-muted">Masukkan nilai (skor 1-5) untuk setiap kriteria pada tema yang Anda pilih</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card p-5">
                <?= $msg; ?>

                <form method="POST" id="formNilai">
                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                    <div class="mb-4">
                        <label class="form-label fw-bold text-primary"><i class="fas fa-book me-2"></i>Pilih Tema Tugas Akhir</label>
                        <select name="id_tema" class="form-select form-select-lg" required>
                            <option value="">-- Cari Tema --</option>
                            <?php while($t = $q_tema->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama_tema']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <hr class="my-4">

                    <div class="row g-3">
                         <?php $no=1; while($k = $q_kriteria->fetch_assoc()): 
                             $field_name = 'c' . $no;
                         ?>
                         <div class="col-md-12">
                             <div class="criteria-card">
                                 <label class="criteria-label">
                                     <span class="criteria-code"><?= htmlspecialchars($k['kode']); ?></span>
                                     <?= htmlspecialchars($k['nama_kriteria']); ?>
                                 </label>
                                 
                                 <div class="score-input position-relative">
                                     <input type="range" name="<?= $field_name; ?>" 
                                            class="score-slider score-range" min="1" max="5" value="3" required 
                                            data-field="<?= $field_name; ?>" data-index="<?= $no; ?>">
                                 </div>

                                 <div class="score-value">
                                     <span class="score-display-val-<?= $no; ?>">3</span> / 5
                                 </div>

                                 <small class="text-muted d-block mt-2">
                                     <i class="fas fa-info-circle me-1"></i>
                                     1 = Terendah | 5 = Tertinggi
                                 </small>
                             </div>
                         </div>
                         <?php $no++; endwhile; ?>
                     </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" name="simpan_nilai" class="btn btn-warning flex-grow-1 py-2">
                            <i class="fas fa-save me-2"></i>SIMPAN & HITUNG RANKING
                        </button>
                        <a href="index.php" class="btn btn-secondary py-2">
                            <i class="fas fa-times me-2"></i>Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.score-range').forEach(slider => {
        slider.addEventListener('input', function() {
            const idx = this.dataset.index;
            const displayVal = document.querySelector('.score-display-val-' + idx);
            if (displayVal) {
                displayVal.textContent = this.value;
                const hue = (this.value - 1) * 30;
                displayVal.style.color = `hsl(${hue}, 100%, 50%)`;
            }
        });
    });
</script>
</body>
</html>
