<?php
/**
 * hash.php - utility to generate bcrypt password hashes
 * Supports both CLI and simple web UI (browser)
 */

$cost = 12;

if (php_sapi_name() === 'cli') {
    // CLI mode (existing behavior)
    $options = ['cost' => $cost];
    $argv0 = array_shift($argv);
    if (count($argv) && in_array($argv[0], ['-h', '--help'])) {
        echo "Usage: php hash.php [password]\n";
        echo "       php hash.php --sql [password]\n";
        exit(0);
    }

    $generate_sql = false;
    if (count($argv) && $argv[0] === '--sql') {
        $generate_sql = true;
        array_shift($argv);
    }

    if (count($argv)) {
        $password = $argv[0];
    } else {
        echo "Enter password: ";
        $password = trim(fgets(STDIN));
    }

    if ($password === '') {
        echo "No password provided.\n";
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, $options);
    if ($hash === false) {
        fwrite(STDERR, "Hashing failed\n");
        exit(1);
    }

    echo "Password: " . $password . PHP_EOL;
    echo "Hash: " . $hash . PHP_EOL;

    if ($generate_sql) {
        $users = [
            'sub_umum',
            'sub_perencanaan',
            'seksi_pemerintahan',
            'seksi_pelayanan',
            'seksi_pemberdayaan',
            'seksi_ekonomi',
            'seksi_ketentraman',
            'admin'
        ];

        echo PHP_EOL . "INSERT INTO `users` (`username`, `password`, `role`) VALUES" . PHP_EOL;
        $lines = [];
        foreach ($users as $u) {
            $role = ($u === 'admin') ? 'admin' : 'user';
            $lines[] = "('" . addslashes($u) . "', '" . addslashes($hash) . "', '" . $role . "')";
        }
        echo implode(",\n", $lines) . ";" . PHP_EOL;
    }

    exit(0);
}

// Web mode: render simple form and result
$hash = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if (trim($pw) === '') {
        $error = 'Password is required.';
    } else {
        $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => $cost]);
        if ($hash === false) {
            $error = 'Hashing failed.';
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Password Hash Generator</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:40px;background:#f7fafc}
    .card{background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;max-width:720px;margin:0 auto}
    label{display:block;margin:8px 0 4px}
    input[type=password]{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px}
    textarea{width:100%;height:120px;padding:10px;border:1px solid #cbd5e1;border-radius:6px;resize:vertical}
    .btn{display:inline-block;padding:8px 14px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;border:none;cursor:pointer}
    .muted{color:#6b7280;font-size:0.9em}
    .row{display:flex;gap:10px;margin-top:10px}
    .error{color:#b91c1c}
  </style>
</head>
<body>
  <div class="card">
    <h2>Password Hash Generator (bcrypt)</h2>
    <p class="muted">Enter a plaintext password and click "Generate Hash". Copy the resulting hash into your database.</p>
    <?php if ($error): ?>
      <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="post">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="new-password" required>
      <div class="row">
        <button class="btn" type="submit">Generate Hash</button>
        <button class="btn" type="button" onclick="document.getElementById('password').value='';document.getElementById('hash').value='';">Clear</button>
      </div>
    </form>

    <?php if ($hash): ?>
      <hr style="margin:18px 0">
      <label for="hash">Hashed Password (bcrypt, cost=<?php echo $cost; ?>)</label>
      <textarea id="hash" readonly><?php echo htmlspecialchars($hash); ?></textarea>
      <div style="margin-top:8px">
        <button class="btn" onclick="copyHash()">Copy Hash</button>
        <span class="muted" style="margin-left:12px">Paste this into the `password` field in your `users` table.</span>
      </div>
    <?php endif; ?>
  </div>

  <script>
    function copyHash(){
      var t = document.getElementById('hash');
      if(!t) return;
      t.select();
      try{document.execCommand('copy'); alert('Hash copied to clipboard');}catch(e){alert('Copy failed — select and copy manually');}
    }
  </script>
</body>
</html>
