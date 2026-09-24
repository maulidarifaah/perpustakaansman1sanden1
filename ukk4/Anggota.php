<?php
require_once '../config/db.php';
require_once '../config/Functions.php';
require_once 'Auth.php';
$active = 'Anggota';

$msg = '';

// ---- CREATE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $nis = trim($_POST['nis']);
    $nama = trim($_POST['nama']);
    $kelas = trim($_POST['kelas']);
    $no_hp = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $cek = $koneksi->query("SELECT id_anggota FROM anggota WHERE nis='".$koneksi->real_escape_string($nis)."' OR username='".$koneksi->real_escape_string($username)."'");
    if ($cek->num_rows > 0) {
        $msg = 'NIS atau Username sudah digunakan.';
    } else {
        $stmt = $koneksi->prepare("INSERT INTO anggota (nis, nama, kelas, no_hp, alamat, username, password) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('sssssss', $nis, $nama, $kelas, $no_hp, $alamat, $username, $password);
        $stmt->execute();
        $msg = 'Anggota baru berhasil ditambahkan.';
    }
}

// ---- UPDATE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id = (int)$_POST['id_anggota'];
    $nis = trim($_POST['nis']);
    $nama = trim($_POST['nama']);
    $kelas = trim($_POST['kelas']);
    $no_hp = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    $username = trim($_POST['username']);
    $status = $_POST['status'];

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $stmt = $koneksi->prepare("UPDATE anggota SET nis=?, nama=?, kelas=?, no_hp=?, alamat=?, username=?, status=?, password=? WHERE id_anggota=?");
        $stmt->bind_param('ssssssssi', $nis, $nama, $kelas, $no_hp, $alamat, $username, $status, $password, $id);
    } else {
        $stmt = $koneksi->prepare("UPDATE anggota SET nis=?, nama=?, kelas=?, no_hp=?, alamat=?, username=?, status=? WHERE id_anggota=?");
        $stmt->bind_param('sssssssi', $nis, $nama, $kelas, $no_hp, $alamat, $username, $status, $id);
    }
    $stmt->execute();
    $msg = 'Data anggota berhasil diperbarui.';
}

// ---- DELETE ----
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $aktifPinjam = $koneksi->query("SELECT COUNT(*) t FROM peminjaman WHERE id_anggota=$id AND status='Dipinjam'")->fetch_assoc()['t'];
    if ($aktifPinjam > 0) {
        $msg = 'Tidak bisa menghapus, anggota masih memiliki buku yang dipinjam.';
    } else {
        $koneksi->query("DELETE FROM anggota WHERE id_anggota=$id");
        $msg = 'Anggota berhasil dihapus.';
    }
}

$cari = trim($_GET['cari'] ?? '');
$sql = "SELECT * FROM anggota";
if ($cari !== '') {
    $c = $koneksi->real_escape_string($cari);
    $sql .= " WHERE nama LIKE '%$c%' OR nis LIKE '%$c%' OR kelas LIKE '%$c%'";
}
$sql .= " ORDER BY id_anggota DESC";
$dataAnggota = $koneksi->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Anggota - Admin</title>
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
      <h1>Kelola Anggota (Siswa)</h1>
      <div class="user-chip">👤 <?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="panel">
      <div class="search-bar">
        <form method="GET" style="display:flex; gap:10px; flex:1;">
          <input type="text" name="cari" placeholder="Cari nama / NIS / kelas..." value="<?= htmlspecialchars($cari) ?>">
          <button class="btn btn-sm" type="submit">Cari</button>
        </form>
        <button class="btn btn-sm btn-green" onclick="document.getElementById('modalTambah').classList.add('show')">+ Tambah Anggota</button>
      </div>

      <table>
        <tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>No HP</th><th>Username</th><th>Status</th><th>Aksi</th></tr>
        <?php while ($a = $dataAnggota->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($a['nis']) ?></td>
          <td><?= htmlspecialchars($a['nama']) ?></td>
          <td><?= htmlspecialchars($a['kelas']) ?></td>
          <td><?= htmlspecialchars($a['no_hp']) ?></td>
          <td><?= htmlspecialchars($a['username']) ?></td>
          <td><?= $a['status']==='Aktif' ? '<span class="badge badge-green">Aktif</span>' : '<span class="badge badge-gray">Nonaktif</span>' ?></td>
          <td class="actions">
            <button class="btn-sm btn-gray" onclick='bukaEdit(<?= json_encode($a) ?>)'>Edit</button>
            <a class="btn-sm btn-danger" style="color:#fff;" href="?hapus=<?= $a['id_anggota'] ?>" onclick="return confirm('Yakin hapus anggota ini?')">Hapus</a>
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
    <h3>Tambah Anggota</h3>
    <form method="POST">
      <input type="hidden" name="aksi" value="tambah">
      <div class="grid-2">
        <div class="form-group"><label>NIS</label><input type="text" name="nis" required></div>
        <div class="form-group"><label>Kelas</label><input type="text" name="kelas" required></div>
      </div>
      <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" required></div>
      <div class="form-group"><label>No HP</label><input type="text" name="no_hp"></div>
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

<!-- MODAL EDIT -->
<div class="modal-bg" id="modalEdit">
  <div class="modal-box">
    <h3>Edit Anggota</h3>
    <form method="POST">
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="id_anggota" id="e_id">
      <div class="grid-2">
        <div class="form-group"><label>NIS</label><input type="text" name="nis" id="e_nis" required></div>
        <div class="form-group"><label>Kelas</label><input type="text" name="kelas" id="e_kelas" required></div>
      </div>
      <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" id="e_nama" required></div>
      <div class="form-group"><label>No HP</label><input type="text" name="no_hp" id="e_nohp"></div>
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
      <button type="button" class="btn btn-gray" style="margin-top:8px;" onclick="document.getElementById('modalEdit').classList.remove('show')">Batal</button>
    </form>
  </div>
</div>

<script>
function bukaEdit(a) {
  document.getElementById('e_id').value = a.id_anggota;
  document.getElementById('e_nis').value = a.nis;
  document.getElementById('e_kelas').value = a.kelas;
  document.getElementById('e_nama').value = a.nama;
  document.getElementById('e_nohp').value = a.no_hp;
  document.getElementById('e_alamat').value = a.alamat;
  document.getElementById('e_username').value = a.username;
  document.getElementById('e_status').value = a.status;
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