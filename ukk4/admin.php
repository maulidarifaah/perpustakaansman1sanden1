<?php
require_once '../config/db.php';
require_once '../config/Functions.php';
require_once 'Auth.php';
$active = 'Admin';

$msg = '';
$msgType = 'success';

$idAdminLogin = (int) $_SESSION['admin_id'];

// ---- TAMBAH ADMIN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $passwordRaw  = $_POST['password'] ?? '';

    if ($nama_lengkap === '' || $username === '' || $passwordRaw === '') {
        $msg = 'Nama lengkap, username, dan password wajib diisi.';
        $msgType = 'error';
    } elseif (strlen($passwordRaw) < 6) {
        $msg = 'Password minimal 6 karakter.';
        $msgType = 'error';
    } else {
        $cek = $koneksi->query("SELECT id_admin FROM admin WHERE username='" . $koneksi->real_escape_string($username) . "'");
        if ($cek->num_rows > 0) {
            $msg = 'Username sudah digunakan, pilih username lain.';
            $msgType = 'error';
        } else {
            $password = password_hash($passwordRaw, PASSWORD_BCRYPT);
            $stmt = $koneksi->prepare("INSERT INTO admin (nama_lengkap, username, password) VALUES (?,?,?)");
            $stmt->bind_param('sss', $nama_lengkap, $username, $password);
            $stmt->execute();
            $msg = 'Admin baru berhasil ditambahkan.';
        }
    }
}

// ---- EDIT ADMIN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id           = (int) $_POST['id_admin'];
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username     = trim($_POST['username'] ?? '');

    if ($nama_lengkap === '' || $username === '') {
        $msg = 'Nama lengkap dan username wajib diisi.';
        $msgType = 'error';
    } else {
        $cek = $koneksi->query("SELECT id_admin FROM admin WHERE username='" . $koneksi->real_escape_string($username) . "' AND id_admin != $id");
        if ($cek->num_rows > 0) {
            $msg = 'Username sudah digunakan oleh admin lain.';
            $msgType = 'error';
        } elseif (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                $msg = 'Password baru minimal 6 karakter.';
                $msgType = 'error';
            } else {
                $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $stmt = $koneksi->prepare("UPDATE admin SET nama_lengkap=?, username=?, password=? WHERE id_admin=?");
                $stmt->bind_param('sssi', $nama_lengkap, $username, $password, $id);
                $stmt->execute();
                if ($id === $idAdminLogin) $_SESSION['admin_nama'] = $nama_lengkap;
                $msg = 'Data admin berhasil diperbarui.';
            }
        } else {
            $stmt = $koneksi->prepare("UPDATE admin SET nama_lengkap=?, username=? WHERE id_admin=?");
            $stmt->bind_param('ssi', $nama_lengkap, $username, $id);
            $stmt->execute();
            if ($id === $idAdminLogin) $_SESSION['admin_nama'] = $nama_lengkap;
            $msg = 'Data admin berhasil diperbarui.';
        }
    }
}

// ---- HAPUS ADMIN ----
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    $totalAdmin = $koneksi->query("SELECT COUNT(*) t FROM admin")->fetch_assoc()['t'];

    if ($id === $idAdminLogin) {
        $msg = 'Tidak bisa menghapus akun yang sedang kamu gunakan sendiri.';
        $msgType = 'error';
    } elseif ($totalAdmin <= 1) {
        $msg = 'Tidak bisa menghapus, minimal harus ada 1 akun admin.';
        $msgType = 'error';
    } else {
        $koneksi->query("DELETE FROM admin WHERE id_admin=$id");
        $msg = 'Admin berhasil dihapus.';
    }
}

$cari = trim($_GET['cari'] ?? '');
$sql = "SELECT * FROM admin";
if ($cari !== '') {
    $c = $koneksi->real_escape_string($cari);
    $sql .= " WHERE nama_lengkap LIKE '%$c%' OR username LIKE '%$c%'";
}
$sql .= " ORDER BY id_admin DESC";
$dataAdmin = $koneksi->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Admin - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin-theme.css">
<style>
  body { background: #F5EFDE !important; }
  .input-password-wrap { position: relative; }
  .input-password-wrap input { padding-right: 40px; width: 100%; box-sizing: border-box; }
  .toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    padding: 0;
  }
</style>
</head>
<body>
<div class="app">
  <?php include 'sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <h1>Kelola Akun Admin</h1>
      <div class="user-chip">👤 <?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
    </div>

    <?php if ($msg): ?>
      <div class="alert <?= $msgType === 'error' ? 'alert-error' : 'alert-success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="search-bar">
        <form method="GET" style="display:flex; gap:10px; flex:1;">
          <input type="text" name="cari" placeholder="Cari nama / username..." value="<?= htmlspecialchars($cari) ?>">
          <button class="btn btn-sm" type="submit">Cari</button>
        </form>
        <button class="btn btn-sm btn-green" onclick="document.getElementById('modalTambah').classList.add('show')">+ Tambah Admin</button>
      </div>

      <table>
        <tr><th>Nama Lengkap</th><th>Username</th><th>Aksi</th></tr>
        <?php if ($dataAdmin->num_rows === 0): ?>
          <tr><td colspan="3" class="text-center">Belum ada data admin.</td></tr>
        <?php endif; ?>
        <?php while ($ad = $dataAdmin->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($ad['nama_lengkap']) ?><?= $ad['id_admin'] === $idAdminLogin ? ' <small style="color:#B8863A;">(Akun kamu)</small>' : '' ?></td>
          <td><?= htmlspecialchars($ad['username']) ?></td>
          <td class="actions">
            <button class="btn-sm btn-gray" onclick='bukaEdit(<?= json_encode($ad) ?>)'>Edit</button>
            <?php if ($ad['id_admin'] !== $idAdminLogin): ?>
              <a class="btn-sm btn-danger" style="color:#fff;" href="?hapus=<?= $ad['id_admin'] ?>" onclick="return confirm('Yakin hapus admin ini?')">Hapus</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal-bg" id="modalTambah">
  <div class="modal-box">
    <h3>Tambah Admin</h3>
    <form method="POST">
      <input type="hidden" name="aksi" value="tambah">
      <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" required></div>
      <div class="grid-2">
        <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-password-wrap">
            <input type="password" name="password" id="pw_tambah" required minlength="6">
            <button type="button" class="toggle-password" onclick="toggleLihat('pw_tambah', this)">👁️</button>
          </div>
        </div>
      </div>
      <button type="submit" class="btn">Simpan</button>
      <button type="button" class="btn btn-gray" style="margin-top:8px;" onclick="document.getElementById('modalTambah').classList.remove('show')">Batal</button>
    </form>
  </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-bg" id="modalEdit">
  <div class="modal-box">
    <h3>Edit Admin</h3>
    <form method="POST">
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="id_admin" id="e_id">
      <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" id="e_nama" required></div>
      <div class="form-group"><label>Username</label><input type="text" name="username" id="e_username" required></div>
      <div class="form-group">
        <label>Password Baru (kosongkan jika tidak diubah)</label>
        <div class="input-password-wrap">
          <input type="password" name="password" id="pw_edit" minlength="6">
          <button type="button" class="toggle-password" onclick="toggleLihat('pw_edit', this)">👁️</button>
        </div>
      </div>
      <button type="submit" class="btn">Update</button>
      <button type="button" class="btn btn-gray" style="margin-top:8px;" onclick="document.getElementById('modalEdit').classList.remove('show')">Batal</button>
    </form>
  </div>
</div>

<script>
function bukaEdit(a) {
  document.getElementById('e_id').value = a.id_admin;
  document.getElementById('e_nama').value = a.nama_lengkap;
  document.getElementById('e_username').value = a.username;
  document.getElementById('modalEdit').classList.add('show');
}

function toggleLihat(inputId, btn) {
  const input = document.getElementById(inputId);
  if (input.type === 'password') {
    input.type = 'text';
    btn.textContent = '🙈';
  } else {
    input.type = 'password';
    btn.textContent = '👁️';
  }
}
</script>
</body>
</html>