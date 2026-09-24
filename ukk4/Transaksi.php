<?php
require_once '../config/db.php';
require_once '../config/Functions.php';
require_once 'Auth.php';
$active = 'Transaksi';

$msg = '';

// ---- TAMBAH PEMINJAMAN (admin input manual) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'pinjam') {
    $id_anggota = (int)$_POST['id_anggota'];
    $id_buku = (int)$_POST['id_buku'];
    $lama = (int)($_POST['lama_hari'] ?: LAMA_PINJAM_HARI);

    $buku = $koneksi->query("SELECT stok_tersedia FROM buku WHERE id_buku=$id_buku")->fetch_assoc();
    if (!$buku || $buku['stok_tersedia'] < 1) {
        $msg = 'Stok buku tidak tersedia.';
    } else {
        $kode = buatKodeTransaksi($koneksi);
        $tglPinjam = date('Y-m-d');
        $tglJatuhTempo = date('Y-m-d', strtotime("+$lama days"));

        $stmt = $koneksi->prepare("INSERT INTO peminjaman (kode_transaksi, id_anggota, id_buku, tanggal_pinjam, tanggal_jatuh_tempo, status) VALUES (?,?,?,?,?,'Dipinjam')");
        $stmt->bind_param('siiss', $kode, $id_anggota, $id_buku, $tglPinjam, $tglJatuhTempo);
        $stmt->execute();

        $koneksi->query("UPDATE buku SET stok_tersedia = stok_tersedia - 1 WHERE id_buku=$id_buku");
        $msg = "Peminjaman berhasil dicatat dengan kode $kode.";
    }
}

// ---- PROSES PERPANJANGAN (oleh admin) ----
if (isset($_GET['perpanjang'])) {
    $id = (int)$_GET['perpanjang'];
    $pinjam = $koneksi->query("SELECT * FROM peminjaman WHERE id_peminjaman=$id")->fetch_assoc();

    if ($pinjam && $pinjam['status'] === 'Dipinjam' && bisaDiperpanjang($pinjam['tanggal_jatuh_tempo'], $pinjam['jumlah_perpanjangan'])) {
        $tglJatuhTempoBaru = date('Y-m-d', strtotime($pinjam['tanggal_jatuh_tempo'] . ' +' . LAMA_PERPANJANGAN_HARI . ' days'));
        $stmt = $koneksi->prepare("UPDATE peminjaman SET tanggal_jatuh_tempo=?, jumlah_perpanjangan=jumlah_perpanjangan+1 WHERE id_peminjaman=?");
        $stmt->bind_param('si', $tglJatuhTempoBaru, $id);
        $stmt->execute();
        $msg = "Peminjaman diperpanjang " . LAMA_PERPANJANGAN_HARI . " hari. Batas baru: " . tanggalIndo($tglJatuhTempoBaru);
    } else {
        $msg = "Peminjaman ini tidak bisa diperpanjang (sudah terlambat atau sudah mencapai batas perpanjangan).";
    }
}

// ---- PROSES PENGEMBALIAN (dengan pilihan kondisi buku) ----
// Dipakai untuk: (a) admin klik "Kembalikan" manual, (b) admin menyetujui
// pengajuan pengembalian dari siswa (id_pengajuan_kembali ikut dikirim).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'proses_kembali') {
    $id = (int)$_POST['id_peminjaman'];
    $idPengajuanKembali = (int)($_POST['id_pengajuan_kembali'] ?? 0);
    $kondisi = $_POST['kondisi_buku'] ?? 'Baik';
    if (!in_array($kondisi, ['Baik', 'Rusak', 'Hilang'])) {
        $kondisi = 'Baik';
    }

    $pinjam = $koneksi->query("SELECT * FROM peminjaman WHERE id_peminjaman=$id")->fetch_assoc();

    if ($pinjam && $pinjam['status'] === 'Dipinjam') {
        $tglKembali = date('Y-m-d');
        $hasil = hitungDenda($pinjam['tanggal_jatuh_tempo'], $tglKembali); // denda telat (jika ada)

        // Denda tambahan jika buku rusak/hilang, nilainya diambil dari tabel pengaturan
        $dendaKondisi = 0;
        if ($kondisi === 'Rusak' || $kondisi === 'Hilang') {
            $namaSetting = $kondisi === 'Rusak' ? 'denda_rusak' : 'denda_hilang';
            $stmtSet = $koneksi->prepare("SELECT nilai FROM pengaturan WHERE nama_pengaturan = ?");
            $stmtSet->bind_param('s', $namaSetting);
            $stmtSet->execute();
            $rowSet = $stmtSet->get_result()->fetch_assoc();
            $dendaKondisi = $rowSet ? (float)$rowSet['nilai'] : 0;
        }

        $dendaTotal = $hasil['denda'] + $dendaKondisi;

        $stmt = $koneksi->prepare("UPDATE peminjaman
            SET tanggal_kembali=?, status=?, jumlah_hari_terlambat=?, denda=?, denda_kondisi=?, kondisi_buku=?
            WHERE id_peminjaman=?");
        $stmt->bind_param('ssiddsi', $tglKembali, $hasil['status'], $hasil['hari_terlambat'], $dendaTotal, $dendaKondisi, $kondisi, $id);
        $stmt->execute();

        // Buku "Hilang" tidak dikembalikan ke rak; "Baik"/"Rusak" tetap menambah stok
        if ($kondisi !== 'Hilang') {
            $koneksi->query("UPDATE buku SET stok_tersedia = stok_tersedia + 1 WHERE id_buku=" . (int)$pinjam['id_buku']);
        }

        // Kalau ini berasal dari pengajuan siswa, tandai pengajuannya sudah disetujui
        if ($idPengajuanKembali > 0) {
            $stmtTandaiAcc = $koneksi->prepare(
                "UPDATE pengajuan_pengembalian SET status='Disetujui', tanggal_diproses=? WHERE id_pengajuan_kembali=?"
            );
            $stmtTandaiAcc->bind_param('si', $tglKembali, $idPengajuanKembali);
            $stmtTandaiAcc->execute();
        }

        $msg = "Buku dikembalikan dengan kondisi: $kondisi.";
        if ($hasil['denda'] > 0) {
            $msg .= " Denda telat {$hasil['hari_terlambat']} hari: " . rupiah($hasil['denda']) . ".";
        }
        if ($dendaKondisi > 0) {
            $msg .= " Denda $kondisi: " . rupiah($dendaKondisi) . ".";
        }
        if ($dendaTotal == 0) {
            $msg .= " Tidak ada denda.";
        }
    }
}

// ---- TOLAK PENGAJUAN PENGEMBALIAN DARI SISWA ----
if (isset($_GET['tolak_kembali'])) {
    $idTolak = (int)$_GET['tolak_kembali'];
    $stmt = $koneksi->prepare(
        "UPDATE pengajuan_pengembalian SET status='Ditolak', tanggal_diproses=? WHERE id_pengajuan_kembali=? AND status='Menunggu'"
    );
    $tglTolak = date('Y-m-d');
    $stmt->bind_param('si', $tglTolak, $idTolak);
    $stmt->execute();
    $msg = 'Pengajuan pengembalian ditolak. Buku dianggap masih dipinjam.';
}

// ---- TANDAI DENDA SUDAH DIBAYAR (manual, tanpa pengajuan QRIS) ----
if (isset($_GET['bayar_denda'])) {
    $id = (int)$_GET['bayar_denda'];
    $koneksi->query("UPDATE peminjaman SET denda_dibayar='Sudah' WHERE id_peminjaman=$id");
    $msg = 'Denda ditandai sudah dibayar.';
}

// ==== TAMBAHAN: SETUJUI PENGAJUAN PEMBAYARAN DENDA (QRIS) DARI SISWA ====
if (isset($_GET['setujui_bayar_denda'])) {
    $idSetuju = (int)$_GET['setujui_bayar_denda'];
    $tglSetuju = date('Y-m-d');

    $pengajuanDenda = $koneksi->query(
        "SELECT * FROM pengajuan_bayar_denda WHERE id_pengajuan_denda=$idSetuju AND status='Menunggu'"
    )->fetch_assoc();

    if (!$pengajuanDenda) {
        $msg = 'Pengajuan pembayaran denda tidak ditemukan atau sudah diproses.';
    } else {
        $koneksi->query("UPDATE peminjaman SET denda_dibayar='Sudah' WHERE id_peminjaman=" . (int)$pengajuanDenda['id_peminjaman']);

        $stmtAccDenda = $koneksi->prepare(
            "UPDATE pengajuan_bayar_denda SET status='Disetujui', tanggal_diproses=? WHERE id_pengajuan_denda=?"
        );
        $stmtAccDenda->bind_param('si', $tglSetuju, $idSetuju);
        $stmtAccDenda->execute();

        $msg = 'Pembayaran denda disetujui, denda ditandai Lunas.';
    }
}

// ==== TAMBAHAN: TOLAK PENGAJUAN PEMBAYARAN DENDA (QRIS) DARI SISWA ====
if (isset($_GET['tolak_bayar_denda'])) {
    $idTolakDenda = (int)$_GET['tolak_bayar_denda'];
    $tglTolakDenda = date('Y-m-d');

    $stmtTolakDenda = $koneksi->prepare(
        "UPDATE pengajuan_bayar_denda SET status='Ditolak', tanggal_diproses=? WHERE id_pengajuan_denda=? AND status='Menunggu'"
    );
    $stmtTolakDenda->bind_param('si', $tglTolakDenda, $idTolakDenda);
    $stmtTolakDenda->execute();

    $msg = 'Pengajuan pembayaran denda ditolak. Denda dianggap masih belum dibayar.';
}
// ==== AKHIR TAMBAHAN ====

// ---- HAPUS TRANSAKSI ----
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $pinjam = $koneksi->query("SELECT * FROM peminjaman WHERE id_peminjaman=$id")->fetch_assoc();
    if ($pinjam && $pinjam['status'] === 'Dipinjam') {
        $koneksi->query("UPDATE buku SET stok_tersedia = stok_tersedia + 1 WHERE id_buku=" . (int)$pinjam['id_buku']);
    }
    $koneksi->query("DELETE FROM peminjaman WHERE id_peminjaman=$id");
    $msg = 'Transaksi berhasil dihapus.';
}

$filterStatus = $_GET['status'] ?? '';
$sql = "SELECT p.*, a.nama AS nama_anggota, a.kelas, b.judul, b.kode_buku
        FROM peminjaman p
        JOIN anggota a ON a.id_anggota = p.id_anggota
        JOIN buku b ON b.id_buku = p.id_buku";
if ($filterStatus !== '') {
    $fs = $koneksi->real_escape_string($filterStatus);
    $sql .= " WHERE p.status = '$fs'";
}
$sql .= " ORDER BY p.id_peminjaman DESC";
$dataTransaksi = $koneksi->query($sql);

$anggotaAktif = $koneksi->query("SELECT id_anggota, nis, nama FROM anggota WHERE status='Aktif' ORDER BY nama");
$bukuTersedia = $koneksi->query("SELECT id_buku, kode_buku, judul, stok_tersedia FROM buku WHERE stok_tersedia > 0 ORDER BY judul");
$anggotaArr = []; while ($r = $anggotaAktif->fetch_assoc()) $anggotaArr[] = $r;
$bukuArr = []; while ($r = $bukuTersedia->fetch_assoc()) $bukuArr[] = $r;

// ---- DAFTAR PENGAJUAN PENGEMBALIAN DARI SISWA YANG MASIH MENUNGGU ----
$pengajuanKembali = $koneksi->query("
    SELECT pk.*, a.nama AS nama_anggota, a.kelas, b.judul, b.kode_buku, p.tanggal_jatuh_tempo, p.jumlah_perpanjangan
    FROM pengajuan_pengembalian pk
    JOIN anggota a ON a.id_anggota = pk.id_anggota
    JOIN buku b ON b.id_buku = pk.id_buku
    JOIN peminjaman p ON p.id_peminjaman = pk.id_peminjaman
    WHERE pk.status = 'Menunggu'
    ORDER BY pk.id_pengajuan_kembali ASC
");

// ==== TAMBAHAN: DAFTAR PENGAJUAN PEMBAYARAN DENDA (QRIS) YANG MASIH MENUNGGU ====
$pengajuanDendaList = $koneksi->query("
    SELECT pd.*, a.nama AS nama_anggota, a.kelas, b.judul, b.kode_buku, p.denda AS denda_saat_ini, p.denda_dibayar
    FROM pengajuan_bayar_denda pd
    JOIN anggota a ON a.id_anggota = pd.id_anggota
    JOIN peminjaman p ON p.id_peminjaman = pd.id_peminjaman
    JOIN buku b ON b.id_buku = p.id_buku
    WHERE pd.status = 'Menunggu'
    ORDER BY pd.id_pengajuan_denda ASC
");
// ==== AKHIR TAMBAHAN ====
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Transaksi Peminjaman - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin-theme.css">
<style>
  body { background: #F5EFDE !important; }
</style>
</head>
<body>
<div class="app">
  <?php include 'sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <h1>Transaksi Peminjaman & Pengembalian</h1>
      <div class="user-chip">👤 <?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <!-- PANEL: PENGAJUAN PENGEMBALIAN DARI SISWA -->
    <?php if ($pengajuanKembali && $pengajuanKembali->num_rows > 0): ?>
    <div class="panel">
      <h2>Pengajuan Pengembalian dari Siswa (<?= $pengajuanKembali->num_rows ?> menunggu)</h2>
      <table>
        <tr>
          <th>Kode Pengajuan</th><th>Anggota</th><th>Buku</th><th>Jatuh Tempo</th>
          <th>Kondisi Diajukan</th><th>Catatan Siswa</th><th>Tgl Ajukan</th><th>Aksi</th>
        </tr>
        <?php while ($pk = $pengajuanKembali->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($pk['kode_pengajuan']) ?></td>
          <td><?= htmlspecialchars($pk['nama_anggota']) ?> <br><small><?= htmlspecialchars($pk['kelas']) ?></small></td>
          <td><?= htmlspecialchars($pk['judul']) ?></td>
          <td><?= tanggalIndo($pk['tanggal_jatuh_tempo']) ?></td>
          <td>
            <span class="badge <?= $pk['kondisi_buku']==='Baik' ? 'badge-green' : 'badge-red' ?>">
              <?= htmlspecialchars($pk['kondisi_buku']) ?>
            </span>
          </td>
          <td><?= $pk['catatan_siswa'] ? htmlspecialchars($pk['catatan_siswa']) : '-' ?></td>
          <td><?= tanggalIndo($pk['tanggal_pengajuan']) ?></td>
          <td class="actions">
            <a class="btn-sm btn-green" style="color:#fff;" href="javascript:void(0)"
               onclick="openModalKembali(<?= (int)$pk['id_peminjaman'] ?>, <?= (int)$pk['id_pengajuan_kembali'] ?>, '<?= htmlspecialchars($pk['kondisi_buku'], ENT_QUOTES) ?>')">
              Setujui & Proses
            </a>
            <a class="btn-sm btn-danger" style="color:#fff;" href="?tolak_kembali=<?= (int)$pk['id_pengajuan_kembali'] ?>" onclick="return confirm('Tolak pengajuan pengembalian ini? Buku akan tetap berstatus Dipinjam.')">Tolak</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
    </div>
    <?php endif; ?>

    <!-- ==== TAMBAHAN: PANEL PENGAJUAN PEMBAYARAN DENDA (QRIS) DARI SISWA ==== -->
    <?php if ($pengajuanDendaList && $pengajuanDendaList->num_rows > 0): ?>
    <div class="panel">
      <h2>Pengajuan Pembayaran Denda via QRIS (<?= $pengajuanDendaList->num_rows ?> menunggu)</h2>
      <table>
        <tr>
          <th>Kode Pengajuan</th><th>Anggota</th><th>Buku</th><th>Nominal Denda</th><th>Tgl Ajukan</th><th>Aksi</th>
        </tr>
        <?php while ($pd = $pengajuanDendaList->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($pd['kode_pengajuan']) ?></td>
          <td><?= htmlspecialchars($pd['nama_anggota']) ?> <br><small><?= htmlspecialchars($pd['kelas']) ?></small></td>
          <td><?= htmlspecialchars($pd['judul']) ?></td>
          <td><?= rupiah($pd['jumlah_denda']) ?></td>
          <td><?= tanggalIndo($pd['tanggal_pengajuan']) ?></td>
          <td class="actions">
            <a class="btn-sm btn-green" style="color:#fff;" href="?setujui_bayar_denda=<?= (int)$pd['id_pengajuan_denda'] ?>"
               onclick="return confirm('Konfirmasi sudah menerima/mengecek pembayaran QRIS ini di mutasi/rekening? Denda akan ditandai Lunas.')">
              Setujui (Tandai Lunas)
            </a>
            <a class="btn-sm btn-danger" style="color:#fff;" href="?tolak_bayar_denda=<?= (int)$pd['id_pengajuan_denda'] ?>"
               onclick="return confirm('Tolak pengajuan pembayaran denda ini? Denda akan dianggap masih belum dibayar.')">
              Tolak
            </a>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
      <p style="margin-top:10px; font-size:12.5px; color:#6b7280;">
        * Sebelum klik <b>Setujui</b>, cek dulu mutasi rekening/e-wallet QRIS untuk memastikan pembayaran dengan nominal yang sesuai benar-benar masuk.
      </p>
    </div>
    <?php endif; ?>
    <!-- ==== AKHIR TAMBAHAN ==== -->

    <div class="panel">
      <div class="search-bar">
        <form method="GET" style="display:flex; gap:10px;">
          <select name="status" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="Dipinjam" <?= $filterStatus==='Dipinjam'?'selected':'' ?>>Dipinjam</option>
            <option value="Dikembalikan Tepat Waktu" <?= $filterStatus==='Dikembalikan Tepat Waktu'?'selected':'' ?>>Dikembalikan Tepat Waktu</option>
            <option value="Dikembalikan Terlambat" <?= $filterStatus==='Dikembalikan Terlambat'?'selected':'' ?>>Dikembalikan Terlambat</option>
          </select>
        </form>
        <button class="btn btn-sm btn-green" onclick="document.getElementById('modalPinjam').classList.add('show')">+ Catat Peminjaman Baru</button>
      </div>

      <table>
        <tr>
          <th>Kode</th><th>Anggota</th><th>Buku</th><th>Tgl Pinjam</th><th>Jatuh Tempo</th>
          <th>Tgl Kembali</th><th>Status</th><th>Denda</th><th>Aksi</th>
        </tr>
        <?php while ($t = $dataTransaksi->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($t['kode_transaksi']) ?></td>
          <td><?= htmlspecialchars($t['nama_anggota']) ?> <br><small><?= htmlspecialchars($t['kelas']) ?></small></td>
          <td><?= htmlspecialchars($t['judul']) ?></td>
          <td><?= tanggalIndo($t['tanggal_pinjam']) ?></td>
          <td><?= tanggalIndo($t['tanggal_jatuh_tempo']) ?></td>
          <td><?= tanggalIndo($t['tanggal_kembali']) ?></td>
          <td>
            <?= badgeStatus($t['status']) ?>
            <?php if (($t['kondisi_buku'] ?? 'Baik') !== 'Baik'): ?>
              <br><small class="badge badge-red"><?= htmlspecialchars($t['kondisi_buku']) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($t['status'] === 'Dipinjam'):
                $preview = hitungDenda($t['tanggal_jatuh_tempo'], date('Y-m-d'));
            ?>
              <?php if ($preview['denda'] > 0): ?>
                <?= rupiah($preview['denda']) ?><br>
                <small class="badge badge-red">Terlambat <?= $preview['hari_terlambat'] ?> hari</small>
              <?php else: ?> - <?php endif; ?>
            <?php elseif ($t['denda'] > 0): ?>
              <?= rupiah($t['denda']) ?><br>
              <small><?= $t['denda_dibayar']==='Sudah' ? '<span class="badge badge-green">Lunas</span>' : '<span class="badge badge-red">Belum Bayar</span>' ?></small>
              <?php if (($t['denda_kondisi'] ?? 0) > 0): ?>
                <br><small>(termasuk denda <?= htmlspecialchars($t['kondisi_buku']) ?>: <?= rupiah($t['denda_kondisi']) ?>)</small>
              <?php endif; ?>
            <?php else: ?> - <?php endif; ?>
          </td>
          <td class="actions">
            <?php if ($t['status'] === 'Dipinjam'): ?>
              <a class="btn-sm btn-green" style="color:#fff;" href="javascript:void(0)" onclick="openModalKembali(<?= $t['id_peminjaman'] ?>, 0, 'Baik')">Kembalikan</a>
              <?php if (bisaDiperpanjang($t['tanggal_jatuh_tempo'], $t['jumlah_perpanjangan'])): ?>
                <a class="btn-sm btn-gray" style="color:#fff;" href="?perpanjang=<?= $t['id_peminjaman'] ?>" onclick="return confirm('Perpanjang masa pinjam <?= LAMA_PERPANJANGAN_HARI ?> hari?')">Perpanjang</a>
              <?php endif; ?>
            <?php elseif ($t['denda'] > 0 && $t['denda_dibayar']==='Belum'): ?>
              <a class="btn-sm btn-gray" style="color:#fff;" href="?bayar_denda=<?= $t['id_peminjaman'] ?>" onclick="return confirm('Tandai denda sudah dibayar?')">Tandai Lunas</a>
            <?php endif; ?>
            <a class="btn-sm btn-danger" style="color:#fff;" href="?hapus=<?= $t['id_peminjaman'] ?>" onclick="return confirm('Yakin hapus transaksi ini?')">Hapus</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </div>
</div>

<!-- MODAL PINJAM -->
<div class="modal-bg" id="modalPinjam">
  <div class="modal-box">
    <h3>Catat Peminjaman Baru</h3>
    <form method="POST">
      <input type="hidden" name="aksi" value="pinjam">
      <div class="form-group">
        <label>Anggota</label>
        <select name="id_anggota" required>
          <option value="">-- Pilih Anggota --</option>
          <?php foreach ($anggotaArr as $a): ?>
            <option value="<?= $a['id_anggota'] ?>"><?= htmlspecialchars($a['nama']) ?> (<?= htmlspecialchars($a['nis']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Buku</label>
        <select name="id_buku" required>
          <option value="">-- Pilih Buku --</option>
          <?php foreach ($bukuArr as $b): ?>
            <option value="<?= $b['id_buku'] ?>"><?= htmlspecialchars($b['judul']) ?> (Stok: <?= $b['stok_tersedia'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Lama Pinjam (hari)</label>
        <input type="number" name="lama_hari" value="<?= LAMA_PINJAM_HARI ?>" min="1">
      </div>
      <button type="submit" class="btn btn-green">Simpan</button>
      <button type="button" class="btn btn-gray" style="margin-top:8px;" onclick="document.getElementById('modalPinjam').classList.remove('show')">Batal</button>
    </form>
  </div>
</div>

<!-- MODAL PENGEMBALIAN + KONDISI BUKU -->
<div class="modal-bg" id="modalKembali">
  <div class="modal-box">
    <h3>Proses Pengembalian</h3>
    <form method="POST">
      <input type="hidden" name="aksi" value="proses_kembali">
      <input type="hidden" name="id_peminjaman" id="kembaliId" value="">
      <input type="hidden" name="id_pengajuan_kembali" id="kembaliPengajuanId" value="0">
      <div class="form-group">
        <label>Kondisi Buku Saat Dikembalikan</label>
        <select name="kondisi_buku" id="kembaliKondisi" required>
          <option value="Baik">Baik (normal)</option>
          <option value="Rusak">Rusak</option>
          <option value="Hilang">Hilang</option>
        </select>
      </div>
      <p style="font-size:13px; color:#666;">
        Denda telat (jika ada) tetap dihitung otomatis. Jika kondisi "Rusak" atau "Hilang" dipilih, denda tambahan akan ditambahkan sesuai nilai pada halaman Pengaturan. Periksa fisik buku sebelum menekan tombol di bawah — kondisi bisa disesuaikan jika berbeda dari yang diajukan siswa.
      </p>
      <button type="submit" class="btn">Proses Pengembalian</button>
      <button type="button" class="btn btn-gray" style="margin-top:8px;" onclick="document.getElementById('modalKembali').classList.remove('show')">Batal</button>
    </form>
  </div>
</div>

<script>
function openModalKembali(idPeminjaman, idPengajuanKembali, kondisiUsulan) {
  document.getElementById('kembaliId').value = idPeminjaman;
  document.getElementById('kembaliPengajuanId').value = idPengajuanKembali;
  document.getElementById('kembaliKondisi').value = kondisiUsulan || 'Baik';
  document.getElementById('modalKembali').classList.add('show');
}
</script>
</body>
</html>