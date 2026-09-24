<?php
require_once '../config/db.php';
require_once '../config/Functions.php';
require_once 'Auth.php';
$active = 'Petugas';

$msg = '';

// ---- TAMBAH PETUGAS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $nip = trim($_POST['nip']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $no_hp = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $cek = $koneksi->query("SELECT id_petugas FROM petugas WHERE username='" . $koneksi->real_escape_string($username) . "'");
    if ($cek->num_rows > 0) {
        $msg = 'Username sudah digunakan.';
    } else {
        $stmt = $koneksi->prepare("INSERT INTO petugas (nip, nama_lengkap, no_hp, alamat, username, password) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssssss', $nip, $nama_lengkap, $no_hp, $alamat, $username, $password);
        $stmt->execute();
        $msg = 'Petugas baru berhasil ditambahkan.';
    }
}

// ---- EDIT / PROFIL PETUGAS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id = (int) $_POST['id_petugas'];
    $nip = trim($_POST['nip']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $no_hp = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    $username = trim($_POST['username']);
    $status = $_POST['status'];

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $stmt = $koneksi->prepare("UPDATE petugas SET nip=?, nama_lengkap=?, no_hp=?, alamat=?, username=?, status=?, password=? WHERE id_petugas=?");
        $stmt->bind_param('sssssssi', $nip, $nama_lengkap, $no_hp, $alamat, $username, $status, $password, $id);
    } else {
        $stmt = $koneksi->prepare("UPDATE petugas SET nip=?, nama_lengkap=?, no_hp=?, alamat=?, username=?, status=? WHERE id_petugas=?");
        $stmt->bind_param('ssssssi', $nip, $nama_lengkap, $no_hp, $alamat, $username, $status, $id);
    }
    $stmt->execute();
    $msg = 'Data petugas berhasil diperbarui.';
}

// ---- HAPUS PETUGAS ----
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    $koneksi->query("DELETE FROM petugas WHERE id_petugas=$id");
    $msg = 'Petugas berhasil dihapus.';
}

$cari = trim($_GET['cari'] ?? '');
$sql = "SELECT * FROM petugas";
if ($cari !== '') {
    $c = $koneksi->real_escape_string($cari);
    $sql .= " WHERE nama_lengkap LIKE '%$c%' OR nip LIKE '%$c%' OR username LIKE '%$c%'";
}
$sql .= " ORDER BY id_petugas DESC";
$dataPetugas = $koneksi->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Petugas - Admin</title>
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
      <h1>Kelola Data Petugas Perpustakaan</h1>
      <div class="user-chip">👤 <?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="panel">
      <div class="search-bar">
        <form method="GET" style="display:flex; gap:10px; flex:1;">
          <input type="text" name="cari" placeholder="Cari nama / NIP / username..." value="<?= htmlspecialchars($cari) ?>">
          <button class="btn btn-sm" type="submit">Cari</button>
        </form>
        <button class="btn btn-sm btn-green" onclick="document.getElementById('modalTambah').classList.add('show')">+ Tambah Petugas</button>
      </div>

      <table>
        <tr><th>NIP</th><th>Nama</th><th>No HP</th><th>Username</th><th>Status</th><th>Aksi</th></tr>
        <?php if ($dataPetugas->num_rows === 0): ?>
          <tr><td colspan="6" class="text-center">Belum ada data petugas.</td></tr>
        <?php endif; ?>
        <?php while ($p = $dataPetugas->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($p['nip']) ?></td>
          <td><?= htmlspecialchars($p['nama_lengkap']) ?></td>
          <td><?= htmlspecialchars($p['no_hp']) ?></td>
          <td><?= htmlspecialchars($p['username']) ?></td>
          <td><?= $p['status']==='Aktif' ? '<span class="badge badge-green">Aktif</span>' : '<span class="badge badge-gray">Nonaktif</span>' ?></td>
          <td class="actions">
            <button class="btn-sm btn-gray" onclick='bukaProfil(<?= json_encode($p) ?>)'>Profil / Edit</button>
            <a class="btn-sm btn-danger" style="color:#fff;" href="?hapus=<?= $p['id_petugas'] ?>" onclick="return confirm('Yakin hapus petugas ini?')">Hapus</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </div>
</div>

<!-- MODAL TAMBAH PETUGAS -->
<div class="modal-bg" id="modalTambah">
  <div class="modal-box">
    <h3>Tambah Petugas</h3>
    <form method="POST">
      <input type="hidden" name="aksi" value="tambah">
      <div class="grid-2">
        <div class="form-group"><label>NIP</label><input type="text" name="nip"></div>
        <div class="form-group"><label>No HP</label><input type="text" name="no_hp"></div>
      </div>
      <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" required></div>
      <div class="form-group"><label>Alamat</label><input type="text" name="alamat"></div>
      <div class="grid-2">
        <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-password-wrap">
            <input type="password" name="password" id="pw_tambah" required>
            <button type="button" class="toggle-password" onclick="toggleLihat('pw_tambah', this)">👁️</button>
          </div>
        </div>
      </div>
      <button type="submit" class="btn">Simpan</button>
      <button type="button" class="btn btn-gray" style="margin-top:8px;" onclick="document.getElementById('modalTambah').classList.remove('show')">Batal</button>
    </form>
  </div>
</div>

<!-- MODAL PROFIL / EDIT PETUGAS -->
<div class="modal-bg" id="modalProfil">
  <div class="modal-box">
    <h3>Profil Petugas</h3>
    <form method="POST">
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="id_petugas" id="e_id">
      <div class="grid-2">
        <div class="form-group"><label>NIP</label><input type="text" name="nip" id="e_nip"></div>
        <div class="form-group"><label>No HP</label><input type="text" name="no_hp" id="e_nohp"></div>
      </div>
      <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" id="e_nama" required></div>
      <div class="form-group"><label>Alamat</label><input type="text" name="alamat" id="e_alamat"></div>
      <div class="grid-2">
        <div class="form-group"><label>Username</label><input type="text" name="username" id="e_username" required></div>
        <div class="form-group"><label>Status</label>
          <select name="status" id="e_status">
            <option value="Aktif">Aktif</option>
            <option value="Nonaktif">Nonaktif</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Password Baru (kosongkan jika tidak diubah)</label>
        <div class="input-password-wrap">
          <input type="password" name="password" id="pw_edit">
          <button type="button" class="toggle-password" onclick="toggleLihat('pw_edit', this)">👁️</button>
        </div>
      </div>
      <button type="submit" class="btn">Update</button>
      <button type="button" class="btn btn-gray" style="margin-top:8px;" onclick="document.getElementById('modalProfil').classList.remove('show')">Batal</button>
    </form>
  </div>
</div>

<script>
function bukaProfil(p) {
  document.getElementById('e_id').value = p.id_petugas;
  document.getElementById('e_nip').value = p.nip;
  document.getElementById('e_nohp').value = p.no_hp;
  document.getElementById('e_nama').value = p.nama_lengkap;
  document.getElementById('e_alamat').value = p.alamat;
  document.getElementById('e_username').value = p.username;
  document.getElementById('e_status').value = p.status;
  document.getElementById('modalProfil').classList.add('show');
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