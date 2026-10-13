<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
include 'koneksi.php';
include 'helper.php';

initSecureSession();

$me = currentUserIdentity($conn);
if (!$me) { http_response_code(403); exit('Forbidden'); }
$user_id = (int) $me['id'];
$level = $me['level'];
$is_admin = ($level === 'admin');
ensureRatingOwnershipSchema($conn);

if (!isset($_GET['type'])) {
    header("Location: index.php");
    exit();
}

$type = $_GET['type'];
$filename = 'SPK_Report_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

if ($type === 'ranking') {
    fputcsv($output, $is_admin
        ? ['Ranking', 'Mahasiswa', 'Tema', 'Skor WSM', 'Status']
        : ['Ranking', 'Tema', 'Skor WSM', 'Status']);
    
    $stmt = $conn->prepare("SELECT * FROM kriteria ORDER BY kode ASC");
    $stmt->execute();
    $bobot_kriteria = [];
    while ($k = $stmt->get_result()->fetch_assoc()) {
        $bobot_kriteria[strtoupper($k['kode'])] = $k['bobot'];
    }
    $stmt->close();
    
    if ($is_admin) {
        $stmt = $conn->prepare("SELECT n.*, t.nama_tema, u.nama_lengkap, u.username
                                FROM penilaian n
                                JOIN tema t ON n.id_tema = t.id
                                LEFT JOIN users u ON t.id_user = u.id
                                ORDER BY n.id DESC");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT n.*, t.nama_tema
                                FROM penilaian n
                                JOIN tema t ON n.id_tema = t.id
                                WHERE n.user_id = ?
                                ORDER BY n.id DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    $q_penilaian = $stmt->get_result();
    $hasil_wsm = [];
    
    while ($row = $q_penilaian->fetch_assoc()) {
        $skor_akhir = ($row['c1'] * ($bobot_kriteria['C1'] ?? 0)) +
                      ($row['c2'] * ($bobot_kriteria['C2'] ?? 0)) +
                      ($row['c3'] * ($bobot_kriteria['C3'] ?? 0)) +
                      ($row['c4'] * ($bobot_kriteria['C4'] ?? 0)) +
                      ($row['c5'] * ($bobot_kriteria['C5'] ?? 0));
        
        $hasil_wsm[] = [
            'tema' => $row['nama_tema'],
            'skor' => $skor_akhir,
            'user' => $row['nama_lengkap'] ?: ($row['username'] ?? ''),
        ];
    }
    $stmt->close();
    
    usort($hasil_wsm, function($a, $b) { return $b['skor'] <=> $a['skor']; });
    
    $rank = 1;
    foreach ($hasil_wsm as $h) {
        $row_csv = [$rank, $h['tema'], number_format($h['skor'], 3),
                    $rank == 1 ? 'Sangat Direkomendasikan' : 'Dipertimbangkan'];
        if ($is_admin) {
            array_splice($row_csv, 1, 0, [$h['user']]);
        }
        fputcsv($output, $row_csv);
        $rank++;
    }
} 
elseif ($type === 'mahasiswa') {
    if (!$is_admin) {
        http_response_code(403);
        exit('Forbidden');
    }
    fputcsv($output, ['No', 'Username', 'Nama Lengkap', 'NPM', 'Kelas', 'Tema Terbaik', 'Skor WSM']);
    
    $stmt = $conn->prepare("SELECT id, username, nama_lengkap, npm, kelas FROM users WHERE level='mahasiswa' ORDER BY id DESC");
    $stmt->execute();
    $query = $stmt->get_result();
    
    $no = 1;
    while ($row = $query->fetch_assoc()) {
        $stmt2 = $conn->prepare("SELECT t.nama_tema, 
            ((n.c1 * (SELECT COALESCE(MAX(CASE WHEN kode='C1' THEN bobot END), 0) FROM kriteria)) + 
             (n.c2 * (SELECT COALESCE(MAX(CASE WHEN kode='C2' THEN bobot END), 0) FROM kriteria)) + 
             (n.c3 * (SELECT COALESCE(MAX(CASE WHEN kode='C3' THEN bobot END), 0) FROM kriteria)) + 
             (n.c4 * (SELECT COALESCE(MAX(CASE WHEN kode='C4' THEN bobot END), 0) FROM kriteria)) + 
             (n.c5 * (SELECT COALESCE(MAX(CASE WHEN kode='C5' THEN bobot END), 0) FROM kriteria))) as total 
            FROM penilaian n 
            JOIN tema t ON n.id_tema = t.id 
            WHERE n.user_id = ? 
            ORDER BY total DESC LIMIT 1");
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $data_rank = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        
        fputcsv($output, [
            $no++,
            $row['username'],
            $row['nama_lengkap'] ?: '',
            $row['npm'] ?: '',
            $row['kelas'] ?: '',
            $data_rank ? $data_rank['nama_tema'] : 'Belum ada penilaian',
            $data_rank ? number_format($data_rank['total'], 3) : '0.000'
        ]);
    }
    $stmt->close();
}

fclose($output);
exit();
?>
