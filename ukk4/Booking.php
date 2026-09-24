<?php
require_once '../config/db.php';
require_once '../config/Functions.php';
require_once 'Auth.php';
$active = 'Booking';

$msg = '';
$msgType = 'success';

$lamaPinjam = defined('LAMA_PINJAM_HARI') ? LAMA_PINJAM_HARI : 7;

// ---- TANDAI SIAP DIAMBIL ----
if (isset($_GET['siap'])) {
    $id = (int) $_GET['siap'];
    $stmt = $koneksi->prepare("UPDATE booking SET status='Siap Diambil' WHERE id_booking=? AND status='Menunggu'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $msg = 'Booking ditandai siap diambil.';
}

// ---- PROSES JADI PEMINJAMAN (siswa datang mengambil buku) ----
if (isset($_GET['proses'])) {
    $id = (int) $_GET['proses'];
    $bk = $koneksi->query("SELECT * FROM booking WHERE id_booking=$id")->fetch_assoc();

    if (!$bk || !in_array($bk['status'], ['Menunggu', 'Siap Diambil'])) {
        $msg = 'Booking tidak valid atau sudah diproses.';
        $msgType = 'error';
    } else {
        $buku = $koneksi->query("SELECT stok_tersedia FROM buku WHERE id_buku=" . (int)$bk['id_buku'])->fetch_assoc();
        $aktifPinjam = $koneksi->query("SELECT COUNT(*) t FROM peminjaman WHERE id_anggota=" . (int)$bk['id_anggota'] . " AND status='Dipinjam'")->fetch_assoc()['t'];

        if (!$buku || $buku['stok_tersedia'] < 1) {
            $msg = 'Stok buku sedang tidak tersedia, booking belum bisa diproses.';
            $msgType = 'error';
        } elseif ($aktifPinjam >= 3) {
            $msg = 'Siswa ini sudah meminjam maksimal 3 buku, booking belum bisa diproses.';
            $msgType = 'error';
        } else {
            $kode = buatKodeTransaksi();
            $tglPinjam = date('Y-m-d');
            $tglJatuhTempo = date('Y-m-d', strtotime("+{$lamaPinjam} days"));

            $stmt = $koneksi->prepare(
                "INSERT INTO peminjaman (kode_transaksi, id_anggota, id_buku, tanggal_pinjam, tanggal_jatuh_tempo, status)
                 VALUES (?, ?, ?, ?, ?, 'Dipinjam')"
            );
            $idAnggota = (int) $bk['id_anggota'];
            $idBuku = (int) $bk['id_buku'];
            $stmt->bind_param('siiss', $kode, $idAnggota, $idBuku, $tglPinjam, $tglJatuhTempo);

            if ($stmt->execute()) {
                $koneksi->query("UPDATE buku SET stok_tersedia = stok_tersedia - 1 WHERE id_buku=$idBuku");
                $stmtBk = $koneksi->prepare("UPDATE booking SET status='Selesai' WHERE id_booking=?");
                $stmtBk->bind_param('i', $id);
                $stmtBk->execute();
                $msg = "Booking berhasil diproses menjadi peminjaman dengan kode $kode.";
            } else {
                $msg = 'Gagal memproses booking menjadi peminjaman.';
                $msgType = 'error';
            }
        }
    }
}

// ---- BATALKAN BOOKING (oleh admin) ----
if (isset($_GET['batal'])) {
    $id = (int) $_GET['batal'];
    $stmt = $koneksi->prepare("UPDATE booking SET status='Dibatalkan' WHERE id_booking=? AND status IN ('Menunggu','Siap Diambil')");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $msg = 'Booking dibatalkan.';
}

// ---- HAPUS RIWAYAT BOOKING (hanya yang sudah Selesai/Dibatalkan) ----
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    $stmt = $koneksi->prepare("DELETE FROM booking WHERE id_booking=? AND status IN ('Selesai','Dibatalkan')");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $msg = 'Riwayat booking dihapus.';
}

$filterStatus = $_GET['status'] ?? '';
$sql = "SELECT bk.*, a.nama AS nama_anggota, a.nis, a.kelas, b.judul, b.kode_buku, b.stok_tersedia
        FROM booking bk
        JOIN anggota a ON a.id_anggota = bk.id_anggota
        JOIN buku b ON b.id_buku = bk.id_buku";
if ($filterStatus !== '') {
    $fs = $koneksi->real_escape_string($filterStatus);
    $sql .= " WHERE bk.status = '$fs'";
}
$sql .= " ORDER BY FIELD(bk.status,'Menunggu','Siap Diambil','Selesai','Dibatalkan'), bk.id_booking DESC";
$dataBooking = $koneksi->query($sql);

function badgeBookingAdmin($status) {
    switch ($status) {
        case 'Menunggu':      return '<span class="badge badge-gray">Menunggu</span>';
        case 'Siap Diambil':  return '<span class="badge badge-blue">Siap Diambil</span>';
        case 'Selesai':       return '<span class="badge badge-green">Selesai</span>';
        case 'Dibatalkan':    return '<span class="badge badge-red">Dibatalkan</span>';
        default:               return htmlspecialchars($status);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Booking - Admin</title>
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
      <h1>Kelola Booking Buku</h1>
      <div style="display:flex; align-items:center;">
        <?php include 'Notifikasi.php'; ?>
        <div class="user-chip">👤 <?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert <?= $msgType === 'error' ? 'alert-error' : 'alert-success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="search-bar">
        <form method="GET" style="display:flex; gap:10px;">
          <select name="status" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="Menunggu" <?= $filterStatus==='Menunggu'?'selected':'' ?>>Menunggu</option>
            <option value="Siap Diambil" <?= $filterStatus==='Siap Diambil'?'selected':'' ?>>Siap Diambil</option>
            <option value="Selesai" <?= $filterStatus==='Selesai'?'selected':'' ?>>Selesai</option>
            <option value="Dibatalkan" <?= $filterStatus==='Dibatalkan'?'selected':'' ?>>Dibatalkan</option>
          </select>
        </form>
      </div>

      <table>
        <tr>
          <th>Kode</th><th>Siswa</th><th>Buku</th><th>Tgl Booking</th><th>Stok Saat Ini</th><th>Status</th><th>Aksi</th>
        </tr>
        <?php if ($dataBooking->num_rows === 0): ?>
          <tr><td colspan="7" class="text-center">Belum ada data booking.</td></tr>
        <?php endif; ?>
        <?php while ($bk = $dataBooking->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($bk['kode_booking']) ?></td>
          <td><?= htmlspecialchars($bk['nama_anggota']) ?> <br><small><?= htmlspecialchars($bk['nis']) ?> - <?= htmlspecialchars($bk['kelas']) ?></small></td>
          <td><?= htmlspecialchars($bk['judul']) ?></td>
          <td><?= tanggalIndo($bk['tanggal_booking']) ?></td>
          <td><?= (int) $bk['stok_tersedia'] ?></td>
          <td><?= badgeBookingAdmin($bk['status']) ?></td>
          <td class="actions">
            <?php if ($bk['status'] === 'Menunggu'): ?>
              <a class="btn-sm btn-gray" style="color:#fff;" href="?siap=<?= $bk['id_booking'] ?>" onclick="return confirm('Tandai booking ini siap diambil?')">Siap Diambil</a>
            <?php endif; ?>
            <?php if (in_array($bk['status'], ['Menunggu','Siap Diambil'])): ?>
              <a class="btn-sm btn-green" style="color:#fff;" href="?proses=<?= $bk['id_booking'] ?>" onclick="return confirm('Proses booking ini menjadi peminjaman? Pastikan siswa sudah ada di perpustakaan.')">Proses Jadi Pinjam</a>
              <a class="btn-sm btn-danger" style="color:#fff;" href="?batal=<?= $bk['id_booking'] ?>" onclick="return confirm('Batalkan booking ini?')">Batalkan</a>
            <?php else: ?>
              <a class="btn-sm btn-danger" style="color:#fff;" href="?hapus=<?= $bk['id_booking'] ?>" onclick="return confirm('Hapus riwayat booking ini?')">Hapus</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </div>
</div>
</body>
</html>