<?php

function initSecureSession() {
    if (!isset($_SESSION['_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['_initialized'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    }
    
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 1800) {
        session_destroy();
        header("Location: login.php?expired=1");
        exit();
    }
    
    if ($_SERVER['REMOTE_ADDR'] !== $_SESSION['ip_address']) {
        session_destroy();
        header("Location: login.php?suspicious=1");
        exit();
    }
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    if (strlen($hash) === 32 && ctype_xdigit($hash)) {
        return md5($password) === $hash;
    }
    return password_verify($password, $hash);
}

function upgradePasswordHash(&$conn, $user_id, $password) {
    $new_hash = hashPassword($password);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $new_hash, $user_id);
    $stmt->execute();
    $stmt->close();
}

function logActivity(&$conn, $user_id, $action, $details = '') {
    if (!$user_id || $user_id <= 0) {
        return;
    }
    $stmt = $conn->prepare("INSERT INTO log_aktivitas (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}

function ensureRatingOwnershipSchema(&$conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $res = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = 'penilaian'
                           AND COLUMN_NAME = 'user_id'");
    $exists = $res && (int) $res->fetch_assoc()['c'] > 0;

    if (!$exists) {
        $conn->query("ALTER TABLE penilaian
                      ADD COLUMN user_id INT(11) NOT NULL DEFAULT 0 AFTER id_tema,
                      ADD INDEX idx_penilaian_user (user_id)");
    }

    $conn->query("UPDATE penilaian p
                  JOIN tema t ON p.id_tema = t.id
                  SET p.user_id = t.id_user
                  WHERE (p.user_id = 0 OR p.user_id IS NULL)
                    AND t.id_user IS NOT NULL AND t.id_user > 0");
}

function currentUserIdentity(&$conn) {
    $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($uid <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT id, username, level FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function getTopicOwner(&$conn, $topic_id) {
    $stmt = $conn->prepare("SELECT id_user FROM tema WHERE id = ?");
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['id_user'] : null;
}

function sanitizeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Request tidak valid. Silakan refresh halaman.');
    }
}

function clampScore(float $val): float {
    return max(1.0, min(5.0, $val));
}

?>
