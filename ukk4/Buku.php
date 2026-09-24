<?php
require_once '../config/db.php';
require_once '../config/Functions.php';
require_once 'Auth.php';
$active = 'Buku';

$msg = '';

// Path penyimpanan upload dan akses gambar
$uploadDir  = '../uploads/'; 
$gambarPath = '/ukk4/uploads/'; 

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// List gambar default
$gambarDefault = [
    'bumi manusia.jpeg',
    'fisika dasar.jpeg',
    'ily.jpeg',
    'laskar pelangi.jpeg',
    'matematika.jpeg',
    'sapiens.jpeg'
];

// ---- CREATE (TAMBAH BUKU) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $kode      = trim($_POST['kode_buku']);
    $judul     = trim($_POST['judul']);
    $pengarang = trim($_POST['pengarang']);
    $penerbit  = trim($_POST['penerbit']);
    $tahun     = (int)$_POST['tahun_terbit'];
    $kategori  = (int)$_POST['id_kategori'];
    $stok      = (int)$_POST['stok_total'];
    $rak       = trim($_POST['rak']);

    $namaGambar = $gambarDefault[array_rand($gambarDefault)];

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        $extDiizinkan = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $extDiizinkan)) {
            $namaGambar = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $_FILES['gambar']['name']);
            move_uploaded_file($_FILES['gambar']['tmp_name'], $uploadDir . $namaGambar);
        }
    }

    $stmt = $koneksi->prepare("INSERT INTO buku (kode_buku, judul, pengarang, penerbit, tahun_terbit, id_kategori, stok_total, stok_tersedia, rak, gambar) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('ssssiiiiss', $kode, $judul, $pengarang, $penerbit, $tahun, $kategori, $stok, $stok, $rak, $namaGambar);
    $stmt->execute();
    $msg = 'Buku baru berhasil ditambahkan.';
}

// ---- UPDATE (EDIT BUKU) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id        = (int)$_POST['id_buku'];
    $kode      = trim($_POST['kode_buku']);
    $judul     = trim($_POST['judul']);
    $pengarang = trim($_POST['pengarang']);
    $penerbit  = trim($_POST['penerbit']);
    $tahun     = (int)$_POST['tahun_terbit'];
    $kategori  = (int)$_POST['id_kategori'];
    $stokTotal = (int)$_POST['stok_total'];
    $rak       = trim($_POST['rak']);

    $lama = $koneksi->query("SELECT stok_total, stok_tersedia, gambar FROM buku WHERE id_buku=$id")->fetch_assoc();
    $selisih = $stokTotal - $lama['stok_total'];
    $stokTersediaBaru = max(0, $lama['stok_tersedia'] + $selisih);

    $namaGambar = $lama['gambar'];

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        $extDiizinkan = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $extDiizinkan)) {
            $namaGambarBaru = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $_FILES['gambar']['name']);

            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $uploadDir . $namaGambarBaru)) {
                if (!empty($lama['gambar']) && !in_array($lama['gambar'], $gambarDefault) && file_exists($uploadDir . $lama['gambar'])) {
                    unlink($uploadDir . $lama['gambar']);
                }
                $namaGambar = $namaGambarBaru;
            }
        }
    }

    $stmt = $koneksi->prepare("UPDATE buku SET kode_buku=?, judul=?, pengarang=?, penerbit=?, tahun_terbit=?, id_kategori=?, stok_total=?, stok_tersedia=?, rak=?, gambar=? WHERE id_buku=?");
    $stmt->bind_param('ssssiiiissi', $kode, $judul, $pengarang, $penerbit, $tahun, $kategori, $stokTotal, $stokTersediaBaru, $rak, $namaGambar, $id);
    $stmt->execute();
    $msg = 'Data buku berhasil diperbarui.';
}

// ---- DELETE (HAPUS BUKU) ----
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $dipinjam = $koneksi->query("SELECT COUNT(*) t FROM peminjaman WHERE id_buku=$id AND status='Dipinjam'")->fetch_assoc()['t'];

    if ($dipinjam > 0) {
        $msg = 'Tidak bisa menghapus, masih ada eksemplar buku ini yang dipinjam.';
    } else {
        $buku = $koneksi->query("SELECT gambar FROM buku WHERE id_buku=$id")->fetch_assoc();
        if ($buku && !empty($buku['gambar']) && !in_array($buku['gambar'], $gambarDefault) && file_exists($uploadDir . $buku['gambar'])) {
            unlink($uploadDir . $buku['gambar']);
        }

        $koneksi->query("DELETE FROM buku WHERE id_buku=$id");
        $msg = 'Buku berhasil dihapus.';
    }
}

$cari = trim($_GET['cari'] ?? '');
$sql = "SELECT b.*, k.nama_kategori FROM buku b LEFT JOIN kategori k ON k.id_kategori=b.id_kategori";
if ($cari !== '') {
    $c = $koneksi->real_escape_string($cari);
    $sql .= " WHERE b.judul LIKE '%$c%' OR b.pengarang LIKE '%$c%' OR b.kode_buku LIKE '%$c%'";
}
$sql .= " ORDER BY b.id_buku DESC";
$dataBuku = $koneksi->query($sql);
$kategoriList = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori");
$kategoriArr = [];
while ($k = $kategoriList->fetch_assoc()) $kategoriArr[] = $k;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Data Buku - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin-theme.css">
<style>
  body { background: #F5EFDE !important; }
    .img-thumb {
        width: 50px;
        height: 70px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #ccc;
        display: block;
        margin: 0 auto;
    }
    table td {
        vertical-align: middle;
    }
</style>
</head>
<body>
<div class="app">
  <?php include 'sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <h1>Kelola Data Buku</h1>
      <div class="user-chip">👤 <?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="panel">
      <div class="search-bar">
        <form method="GET" style="display:flex; gap:10px; flex:1;">
          <input type="text" name="cari" placeholder="Cari judul / pengarang / kode buku..." value="<?= htmlspecialchars($cari) ?>">
          <button class="btn btn-sm" type="submit">Cari</button>
        </form>
        <button class="btn btn-sm btn-green" onclick="document.getElementById('modalTambah').classList.add('show')">+ Tambah Buku</button>
      </div>

      <table>
        <tr>
          <th>Cover</th>
          <th>Kode</th>
          <th>Judul</th>
          <th>Pengarang</th>
          <th>Kategori</th>
          <th>Tahun</th>
          <th>Stok Total</th>
          <th>Stok Tersedia</th>
          <th>Rak</th>
          <th>Aksi</th>
        </tr>
        <?php while ($b = $dataBuku->fetch_assoc()): ?>
        <tr>
          <td style="text-align: center;">
            <?php 
                // Jika kolom gambar di DB terisi, pakai gambar tersebut
                if (!empty($b['gambar'])) {
                    $file = $b['gambar'];
                } else {
                                // Jika DB kosong, cocokkan otomatis berdasarkan judul buku
                                $judulLower = strtolower($b['judul']);

                                if (strpos($judulLower, 'bumi manusia') !== false) {
                                    $file = 'bumi manusia.jpeg';
                                } elseif (strpos($judulLower, 'fisika') !== false) {
                                    $file = 'fisika dasar.jpeg';
                                } elseif (strpos($judulLower, 'ily') !== false) {
                                    $file = 'ily.jpeg';
                                } elseif (strpos($judulLower, 'laskar pelangi') !== false) {
                                    $file = 'laskar pelangi.jpeg';
                                } elseif (strpos($judulLower, 'matematika') !== false) {
                                    $file = 'matematika.jpeg';
                                } elseif (strpos($judulLower, 'sapiens') !== false) {
                                    $file = 'sapiens.jpeg';
                                } elseif (strpos($judulLower, 'laut bercerita') !== false) {
                                    $file = 'nov mal.jpeg';
                                } elseif (strpos($judulLower, 'malioboro mindnight') !== false) {
                                    $file = 'malio.jpeg';
                                } elseif (strpos($judulLower, 'marveluna') !== false) {
                                    $file = 'marvel.jpeg';
                                } elseif (strpos($judulLower, 'musuh tapi menikah') !== false) {
                                    $file = 'musuh menikah.jpeg';
                                } elseif (strpos($judulLower, 'dosen bucin') !== false) {
                                    $file = 'dsn bcn.jpeg';
                                } elseif (strpos($judulLower, 'winters in tokyo') !== false) {
                                    $file = 'winters.jpeg';
                                } else {
                                    $file = 'bumi manusia.jpeg'; // Default cadangan
                                }
                            }

                $src = $gambarPath . rawurlencode($file);
            ?>
            <img src="<?= $src ?>" alt="Cover Buku" class="img-thumb">
          </td>
          <td><?= htmlspecialchars($b['kode_buku']) ?></td>
          <td><?= htmlspecialchars($b['judul']) ?></td>
          <td><?= htmlspecialchars($b['pengarang']) ?></td>
          <td><?= htmlspecialchars($b['nama_kategori'] ?? '-') ?></td>
          <td><?= htmlspecialchars($b['tahun_terbit']) ?></td>
          <td><?= $b['stok_total'] ?></td>
          <td><?= $b['stok_tersedia'] ?></td>
          <td><?= htmlspecialchars($b['rak']) ?></td>
          <td class="actions">
            <button class="btn-sm btn-gray" onclick='bukaEdit(<?= json_encode($b) ?>)'>Edit</button>
            <a class="btn-sm btn-danger" style="color:#fff;" href="?hapus=<?= $b['id_buku'] ?>" onclick="return confirm('Yakin hapus buku ini?')">Hapus</a>
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
    <h3>Tambah Buku</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="aksi" value="tambah">
      <div class="form-group"><label>Kode Buku</label><input type="text" name="kode_buku" required></div>
      <div class="form-group"><label>Judul</label><input type="text" name="judul" required></div>
      <div class="form-group"><label>Pengarang</label><input type="text" name="pengarang" required></div>
      <div class="form-group"><label>Penerbit</label><input type="text" name="penerbit"></div>
      <div class="grid-2">
        <div class="form-group"><label>Tahun Terbit</label><input type="number" name="tahun_terbit" value="<?= date('Y') ?>"></div>
        <div class="form-group"><label>Rak</label><input type="text" name="rak"></div>
      </div>
      <div class="form-group"><label>Gambar Cover (Opsional)</label><input type="file" name="gambar" accept="image/*"></div>
      <div class="form-group">
        <label>Kategori</label>
        <select name="id_kategori">
          <?php foreach ($kategoriArr as $k): ?>
            <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Stok Total</label><input type="number" name="stok_total" min="0" required></div>
      <button type="submit" class="btn">Simpan</button>
      <button type="button" class="btn btn-gray" style="margin-top:8px;" onclick="document.getElementById('modalTambah').classList.remove('show')">Batal</button>
    </form>
  </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-bg" id="modalEdit">
  <div class="modal-box">
    <h3>Edit Buku</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="id_buku" id="e_id">
      <div class="form-group"><label>Kode Buku</label><input type="text" name="kode_buku" id="e_kode" required></div>
      <div class="form-group"><label>Judul</label><input type="text" name="judul" id="e_judul" required></div>
      <div class="form-group"><label>Pengarang</label><input type="text" name="pengarang" id="e_pengarang" required></div>
      <div class="form-group"><label>Penerbit</label><input type="text" name="penerbit" id="e_penerbit"></div>
      <div class="grid-2">
        <div class="form-group"><label>Tahun Terbit</label><input type="number" name="tahun_terbit" id="e_tahun"></div>
        <div class="form-group"><label>Rak</label><input type="text" name="rak" id="e_rak"></div>
      </div>
      <div class="form-group">
        <label>Gambar Cover Baru (Opsional)</label>
        <input type="file" name="gambar" accept="image/*">
      </div>
      <div class="form-group">
        <label>Kategori</label>
        <select name="id_kategori" id="e_kategori">
          <?php foreach ($kategoriArr as $k): ?>
            <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Stok Total</label><input type="number" name="stok_total" id="e_stok" min="0" required></div>
      <button type="submit" class="btn">Update</button>
      <button type="button" class="btn btn-gray" style="margin-top:8px;" onclick="document.getElementById('modalEdit').classList.remove('show')">Batal</button>
    </form>
  </div>
</div>

<script>
function bukaEdit(b) {
  document.getElementById('e_id').value = b.id_buku;
  document.getElementById('e_kode').value = b.kode_buku;
  document.getElementById('e_judul').value = b.judul;
  document.getElementById('e_pengarang').value = b.pengarang;
  document.getElementById('e_penerbit').value = b.penerbit;
  document.getElementById('e_tahun').value = b.tahun_terbit;
  document.getElementById('e_rak').value = b.rak;
  document.getElementById('e_kategori').value = b.id_kategori;
  document.getElementById('e_stok').value = b.stok_total;
  document.getElementById('modalEdit').classList.add('show');
}
</script>
</body>
</html>