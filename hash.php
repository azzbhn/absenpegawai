<?php
/**
 * hash.php
 * Simple script untuk:
 * - Meng-hash password (via web form atau CLI)
 * - Memverifikasi password plain terhadap hash yang sudah dibuat
 *
 * Keamanan: menggunakan password_hash() & password_verify() built-in PHP.
 *
 * Usage (web):
 *  - Buka di browser, masukkan password lalu "Hash" atau "Verify".
 *
 * Usage (CLI):
 *  - php hash.php hash "passwordPlain"
 *  - php hash.php verify "passwordPlain" '$2y$10$...'
 */

// --- Helper functions ---
function create_hash(string $plain): string {
    // PASSWORD_DEFAULT memilih algoritma yang aman (saat ini bcrypt/argon2 tergantung versi PHP)
    // Option contoh: ['cost' => 12] untuk bcrypt; jangan ubah kalau tidak perlu.
    return password_hash($plain, PASSWORD_DEFAULT);
}

function verify_hash(string $plain, string $hash): bool {
    if (empty($hash)) return false;
    return password_verify($plain, $hash);
}

// --- CLI mode ---
if (php_sapi_name() === 'cli') {
    $argv_count = isset($argv) ? count($argv) : 0;

    if ($argv_count < 3) {
        echo "Usage (CLI):\n";
        echo "  php hash.php hash \"passwordPlain\"\n";
        echo "  php hash.php verify \"passwordPlain\" \"existingHash\"\n";
        exit(0);
    }

    $cmd = $argv[1];

    if ($cmd === 'hash') {
        $plain = $argv[2];
        $hash = create_hash($plain);
        echo "Plain  : {$plain}\n";
        echo "Hash   : {$hash}\n";
        exit(0);
    } elseif ($cmd === 'verify') {
        if ($argv_count < 4) {
            echo "verify requires two arguments: verify \"passwordPlain\" \"existingHash\"\n";
            exit(1);
        }
        $plain = $argv[2];
        $hash  = $argv[3];
        $ok = verify_hash($plain, $hash);
        echo $ok ? "VERIFIED: password matches the hash\n" : "FAILED: password does NOT match the hash\n";
        exit($ok ? 0 : 1);
    } else {
        echo "Unknown command: {$cmd}\n";
        exit(1);
    }
}

// --- Web mode (simple form) ---
$action = $_POST['action'] ?? null;
$plain  = $_POST['plain'] ?? '';
$hash   = $_POST['hash'] ?? '';
$result = '';

if ($action === 'hash' && $plain !== '') {
    $newHash = create_hash($plain);
    $result = "Hash: <code>" . htmlspecialchars($newHash) . "</code>";
} elseif ($action === 'verify' && $plain !== '' && $hash !== '') {
    $ok = verify_hash($plain, $hash);
    $result = $ok ? '<span style="color:green;font-weight:600">TERVERIFIKASI: Password cocok dengan hash.</span>'
                  : '<span style="color:red;font-weight:600">GAGAL: Password tidak cocok dengan hash.</span>';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Hash Password - hash.php</title>
  <style>
    body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; padding: 24px; background:#f7fafc; color:#111827; }
    .card { background:white; border-radius:8px; padding:16px; max-width:720px; margin:12px auto; box-shadow:0 6px 18px rgba(0,0,0,0.06); }
    input, textarea { width:100%; padding:8px 10px; margin-top:6px; margin-bottom:12px; border:1px solid #e5e7eb; border-radius:6px; font-size:14px; }
    button { background:#1f2937; color:white; padding:8px 12px; border-radius:6px; border:none; cursor:pointer; }
    pre { background:#111827; color:#f8fafc; padding:10px; border-radius:6px; overflow:auto; }
    code { background:#eef2ff; padding:2px 6px; border-radius:4px; }
  </style>
</head>
<body>
  <div class="card">
    <h2>Hash & Verify Password</h2>
    <p>Gunakan ini untuk membuat hash dari password atau memverifikasinya. Script menggunakan <code>password_hash()</code> & <code>password_verify()</code>.</p>

    <form method="post" style="margin-top:12px">
      <label>Plain password</label>
      <input type="text" name="plain" value="<?php echo htmlspecialchars($plain); ?>" placeholder="ketik password di sini" required>

      <label>Hash (untuk verifikasi) — kosongkan jika ingin membuat hash baru</label>
      <input type="text" name="hash" value="<?php echo htmlspecialchars($hash); ?>" placeholder="tempelkan hash jika ingin verify">

      <div style="display:flex; gap:8px;">
        <button type="submit" name="action" value="hash">Buat Hash</button>
        <button type="submit" name="action" value="verify">Verifikasi</button>
      </div>
    </form>

    <div style="margin-top:14px;">
      <?php if ($result !== ''): ?>
        <div><strong>Hasil:</strong></div>
        <div style="margin-top:8px;"><?php echo $result; ?></div>
      <?php endif; ?>
    </div>

    <hr style="margin:18px 0">
    <div style="font-size:13px;color:#6b7280">
      <strong>Catatan keamanan:</strong>
      <ul>
        <li>Jangan simpan password plain di database — selalu simpan <code>hash</code> saja.</li>
        <li>Kembalian <code>password_hash()</code> sudah mengandung salt otomatis.</li>
        <li>Gunakan <code>password_needs_rehash()</code> jika ingin memigrasi ke algoritma/option baru.</li>
      </ul>
    </div>
  </div>
</body>
</html>
