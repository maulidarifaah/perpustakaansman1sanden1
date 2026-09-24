<?php
require_once '../config/db.php';
require_once '../config/functions.php';
require_once 'Auth.php';
$active = 'Laporan';

// Default periode: awal bulan ini sampai hari ini
$tanggalAwal  = $_GET['dari'] ?? date('Y-m-01');
$tanggalAkhir = $_GET['sampai'] ?? date('Y-m-d');

// Validasi sederhana format tanggal
if (!strtotime($tanggalAwal)) $tanggalAwal = date('Y-m-01');
if (!strtotime($tanggalAkhir)) $tanggalAkhir = date('Y-m-d');

$awalSql  = $koneksi->real_escape_string($tanggalAwal);
$akhirSql = $koneksi->real_escape_string($tanggalAkhir);

// ===================== PAGINATION SETUP =====================
$perHalaman = 15;

$halTransaksi = isset($_GET['hal_transaksi']) ? max(1, (int) $_GET['hal_transaksi']) : 1;
$offsetTransaksi = ($halTransaksi - 1) * $perHalaman;

$halTerlambat = isset($_GET['hal_terlambat']) ? max(1, (int) $_GET['hal_terlambat']) : 1;
$offsetTerlambat = ($halTerlambat - 1) * $perHalaman;

function urlHalaman($paramName, $page) {
    $params = $_GET;
    $params[$paramName] = $page;
    return '?' . http_build_query($params);
}

function renderPagination($paramName, $halamanSekarang, $totalHalaman, $anchor) {
    if ($totalHalaman <= 1) return;
    echo '<div class="pagination-nav no-print">';
    if ($halamanSekarang > 1) {
        echo '<a href="' . urlHalaman($paramName, $halamanSekarang - 1) . '#' . $anchor . '" class="page-link">&laquo; Sebelumnya</a>';
    }
    for ($i = 1; $i <= $totalHalaman; $i++) {
        $aktif = $i === $halamanSekarang ? ' page-active' : '';
        echo '<a href="' . urlHalaman($paramName, $i) . '#' . $anchor . '" class="page-link' . $aktif . '">' . $i . '</a>';
    }
    if ($halamanSekarang < $totalHalaman) {
        echo '<a href="' . urlHalaman($paramName, $halamanSekarang + 1) . '#' . $anchor . '" class="page-link">Berikutnya &raquo;</a>';
    }
    echo '</div>';
}

// ---- Ringkasan Umum (kondisi saat ini, bukan per periode) ----
$totalBukuJudul   = $koneksi->query("SELECT COUNT(*) t FROM buku")->fetch_assoc()['t'];
$totalEksemplar   = $koneksi->query("SELECT COALESCE(SUM(stok_total),0) t FROM buku")->fetch_assoc()['t'];
$totalAnggotaAktif= $koneksi->query("SELECT COUNT(*) t FROM anggota WHERE status='Aktif'")->fetch_assoc()['t'];
$sedangDipinjamSkrg = $koneksi->query("SELECT COUNT(*) t FROM peminjaman WHERE status='Dipinjam'")->fetch_assoc()['t'];

// ---- Statistik dalam Periode yang Dipilih (berdasarkan tanggal_pinjam) ----
$totalTransaksiPeriode = $koneksi->query("
    SELECT COUNT(*) t FROM peminjaman
    WHERE tanggal_pinjam BETWEEN '$awalSql' AND '$akhirSql'
")->fetch_assoc()['t'];

$kembaliTepatWaktu = $koneksi->query("
    SELECT COUNT(*) t FROM peminjaman
    WHERE status='Dikembalikan Tepat Waktu' AND tanggal_kembali BETWEEN '$awalSql' AND '$akhirSql'
")->fetch_assoc()['t'];

$kembaliTerlambat = $koneksi->query("
    SELECT COUNT(*) t FROM peminjaman
    WHERE status='Dikembalikan Terlambat' AND tanggal_kembali BETWEEN '$awalSql' AND '$akhirSql'
")->fetch_assoc()['t'];

$totalDendaPeriode = $koneksi->query("
    SELECT COALESCE(SUM(denda),0) t FROM peminjaman
    WHERE tanggal_kembali BETWEEN '$awalSql' AND '$akhirSql'
")->fetch_assoc()['t'];

$dendaLunasPeriode = $koneksi->query("
    SELECT COALESCE(SUM(denda),0) t FROM peminjaman
    WHERE tanggal_kembali BETWEEN '$awalSql' AND '$akhirSql' AND denda_dibayar='Sudah'
")->fetch_assoc()['t'];

$dendaBelumPeriode = $koneksi->query("
    SELECT COALESCE(SUM(denda),0) t FROM peminjaman
    WHERE tanggal_kembali BETWEEN '$awalSql' AND '$akhirSql' AND denda_dibayar='Belum'
")->fetch_assoc()['t'];

$totalPerpanjanganPeriode = $koneksi->query("
    SELECT COALESCE(SUM(jumlah_perpanjangan),0) t FROM peminjaman
    WHERE tanggal_pinjam BETWEEN '$awalSql' AND '$akhirSql'
")->fetch_assoc()['t'];

// ---- Buku Terpopuler dalam Periode ----
$bukuTerpopuler = $koneksi->query("
    SELECT b.judul, b.pengarang, COUNT(*) jumlah_pinjam
    FROM peminjaman p JOIN buku b ON b.id_buku = p.id_buku
    WHERE p.tanggal_pinjam BETWEEN '$awalSql' AND '$akhirSql'
    GROUP BY p.id_buku
    ORDER BY jumlah_pinjam DESC
    LIMIT 5
");

// ---- Daftar Transaksi dalam Periode (untuk lampiran laporan) — DENGAN PAGINATION ----
$totalDaftarTransaksi = $koneksi->query("
    SELECT COUNT(*) t
    FROM peminjaman p
    JOIN anggota a ON a.id_anggota = p.id_anggota
    JOIN buku b ON b.id_buku = p.id_buku
    WHERE p.tanggal_pinjam BETWEEN '$awalSql' AND '$akhirSql'
")->fetch_assoc()['t'];
$totalHalTransaksi = max(1, (int) ceil($totalDaftarTransaksi / $perHalaman));
if ($halTransaksi > $totalHalTransaksi) {
    $halTransaksi = $totalHalTransaksi;
    $offsetTransaksi = ($halTransaksi - 1) * $perHalaman;
}

$daftarTransaksi = $koneksi->query("
    SELECT p.kode_transaksi, a.nama AS nama_anggota, a.kelas, b.judul,
           p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.tanggal_kembali, p.status, p.denda
    FROM peminjaman p
    JOIN anggota a ON a.id_anggota = p.id_anggota
    JOIN buku b ON b.id_buku = p.id_buku
    WHERE p.tanggal_pinjam BETWEEN '$awalSql' AND '$akhirSql'
    ORDER BY p.tanggal_pinjam ASC
    LIMIT $perHalaman OFFSET $offsetTransaksi
");

// ---- Laporan Keterlambatan Pengembalian — DENGAN PAGINATION ----
$kondisiTerlambat = "
    WHERE p.tanggal_pinjam BETWEEN '$awalSql' AND '$akhirSql'
    AND (
        p.status = 'Dikembalikan Terlambat'
        OR (p.status = 'Dipinjam' AND p.tanggal_jatuh_tempo < CURDATE())
    )
";

$jumlahItemTerlambat = $koneksi->query("
    SELECT COUNT(*) t
    FROM peminjaman p
    JOIN anggota a ON a.id_anggota = p.id_anggota
    JOIN buku b ON b.id_buku = p.id_buku
    $kondisiTerlambat
")->fetch_assoc()['t'];
$totalHalTerlambat = max(1, (int) ceil($jumlahItemTerlambat / $perHalaman));
if ($halTerlambat > $totalHalTerlambat) {
    $halTerlambat = $totalHalTerlambat;
    $offsetTerlambat = ($halTerlambat - 1) * $perHalaman;
}

$keterlambatan = $koneksi->query("
    SELECT p.kode_transaksi, a.nama AS nama_anggota, a.kelas, b.judul, b.kode_buku,
           p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.tanggal_kembali, p.status,
           p.jumlah_hari_terlambat, p.denda, p.denda_dibayar,
           DATEDIFF(COALESCE(p.tanggal_kembali, CURDATE()), p.tanggal_jatuh_tempo) AS hari_terlambat_dihitung
    FROM peminjaman p
    JOIN anggota a ON a.id_anggota = p.id_anggota
    JOIN buku b ON b.id_buku = p.id_buku
    $kondisiTerlambat
    ORDER BY p.tanggal_jatuh_tempo ASC
    LIMIT $perHalaman OFFSET $offsetTerlambat
");

function badgeStatusLaporan($status) {
    switch ($status) {
        case 'Dikembalikan Tepat Waktu': return '<span class="badge badge-green">' . htmlspecialchars($status) . '</span>';
        case 'Dikembalikan Terlambat':   return '<span class="badge badge-red">' . htmlspecialchars($status) . '</span>';
        case 'Dipinjam':                 return '<span class="badge badge-blue">' . htmlspecialchars($status) . '</span>';
        default:                          return htmlspecialchars($status);
    }
}

$totalDendaKeterlambatan = $koneksi->query("
    SELECT COALESCE(SUM(p.denda),0) t
    FROM peminjaman p
    $kondisiTerlambat
")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan untuk Kepala Sekolah - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin-theme.css">
<style>
  body { background:#F5EFDE !important; }

  .kertas {
    width: 794px;
    min-height: 1123px;
    margin: 24px auto;
    background: #ffffff;
    padding: 22mm 18mm 26mm 18mm;
    font-family: "Times New Roman", Georgia, serif;
    color: #1a1a1a;
    line-height: 1.6;
    box-shadow: 0 0 18px rgba(0,0,0,0.18);
    border: 1px solid #d1d5db;
    position: relative;
    box-sizing: border-box;
    scroll-margin-top: 20px;
  }
  .kertas .no-halaman {
    position: absolute;
    bottom: 10mm;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 11px;
    color: #6b7280;
    font-family: "Times New Roman", Georgia, serif;
  }

  #area-cetak h2 {
    font-family: "Times New Roman", Georgia, serif;
    font-size: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 1.5px solid #111827;
    padding-bottom: 5px;
    margin-top: 0;
    margin-bottom: 14px;
  }
  #area-cetak table {
    width: 100%;
    border-collapse: collapse;
    font-family: "Times New Roman", Georgia, serif;
    font-size: 12.5px;
    margin-bottom: 22px;
  }
  #area-cetak table th,
  #area-cetak table td {
    border: 1px solid #111827;
    padding: 7px 10px;
    text-align: left;
    vertical-align: middle;
  }
  #area-cetak table th {
    background: #eef0f3;
    font-weight: 700;
    text-align: center;
  }
  #area-cetak table td:first-child { text-align: center; }
  #area-cetak .badge {
    font-family: Arial, sans-serif;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 3px;
    display: inline-block;
  }
  #area-cetak p { font-size: 13px; }

  .laporan-kop { text-align:center; margin-bottom: 22px; }
  .laporan-kop h2 {
    font-size: 19px; font-weight: 700; border-bottom: none;
    margin: 0 0 4px 0; padding: 0; text-transform: none;
  }
  .laporan-kop p { font-size: 13px; color:#374151; margin: 2px 0; }
  .laporan-kop hr { margin-top:12px; border: none; border-top: 3px double #111827; }

  .ttd-area { display:flex; justify-content:space-between; margin-top:70px; padding: 0 20px; font-family:"Times New Roman", Georgia, serif; }
  .ttd-box { text-align:center; font-size:13.5px; }
  .ttd-box .garis { margin-top:65px; border-top:1px solid #111827; padding-top:6px; min-width:210px; }

  html { scroll-behavior: smooth; }

  .nav-laporan {
    max-width: 794px;
    margin: 0 auto 16px auto;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 14px 18px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  }
  .nav-laporan .nav-judul {
    font-size: 12.5px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 10px;
  }
  .nav-laporan .nav-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .nav-laporan .nav-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: #f3f4f6;
    color: #111827;
    text-decoration: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid transparent;
    transition: background .15s ease, border-color .15s ease;
  }
  .nav-laporan .nav-item .nav-no {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px; height: 20px;
    background: #2563eb;
    color: #fff;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 700;
  }
  .nav-laporan .nav-item:hover {
    background: #e5e7eb;
    border-color: #d1d5db;
  }

  /* ===== PAGINATION ===== */
  .pagination-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    margin: 4px 0 20px 0;
    font-family: Arial, sans-serif;
  }
  .pagination-nav .page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #fff;
    color: #374151;
    text-decoration: none;
    font-size: 12.5px;
    transition: background .15s ease, border-color .15s ease;
  }
  .pagination-nav .page-link:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
  }
  .pagination-nav .page-link.page-active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
    font-weight: 700;
  }

  @media print {
    body { background: #fff; }
    .sidebar, .topbar, .no-print { display: none !important; }
    .main { padding: 0 !important; }
    .nav-laporan { display: none !important; }
    .tombol-kembali { display: none !important; }
    .kembali-halaman { display: none !important; }
    .kertas {
      box-shadow: none !important;
      border: none !important;
      margin: 0;
      width: auto;
      min-height: 100vh;
      page-break-after: always;
    }
    .kertas:last-child { page-break-after: auto; }
    tr { page-break-inside: avoid; }
    @page { size: A4; margin: 12mm; }
  }

  @media (max-width: 640px) {
    .nav-laporan .nav-list { flex-direction: column; }
  }

  .tombol-kembali {
    position: fixed;
    bottom: 26px;
    right: 26px;
    background: #2563eb;
    color: #fff !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    padding: 12px 18px;
    border-radius: 30px;
    box-shadow: 0 4px 14px rgba(37,99,235,0.4);
    z-index: 999;
    transition: background .15s ease, transform .15s ease;
  }
  .tombol-kembali:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
  }

  .kembali-halaman {
    display: inline-block;
    margin-top: 14px;
    font-size: 12px;
    color: #2563eb;
    text-decoration: none;
    font-family: Arial, sans-serif;
  }
  .kembali-halaman:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="app">
  <?php include 'sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <h1>Laporan untuk Kepala Sekolah</h1>
      <div class="user-chip">👤 <?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
    </div>

    <div class="panel no-print">
      <form method="GET" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
        <div class="form-group" style="margin-bottom:0;">
          <label>Dari Tanggal</label>
          <input type="date" name="dari" value="<?= htmlspecialchars($tanggalAwal) ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Sampai Tanggal</label>
          <input type="date" name="sampai" value="<?= htmlspecialchars($tanggalAkhir) ?>">
        </div>
        <button class="btn btn-sm" type="submit" style="width:auto;">Tampilkan</button>
        <button class="btn btn-sm btn-green" type="button" style="width:auto;" onclick="window.print()">🖨️ Cetak Laporan</button>
      </form>
    </div>

    <!-- ===== MENU NAVIGASI LAPORAN (klik langsung loncat ke bagian yang dicari) ===== -->
    <div class="nav-laporan no-print" id="nav-laporan">
      <div class="nav-judul">Loncat ke bagian:</div>
      <div class="nav-list">
        <a href="#halaman1" class="nav-item"><span class="nav-no">1</span> Kondisi Perpustakaan</a>
        <a href="#halaman2" class="nav-item"><span class="nav-no">2</span> Ringkasan Transaksi</a>
        <a href="#halaman3" class="nav-item"><span class="nav-no">3</span> Buku Terpopuler</a>
        <a href="#halaman4" class="nav-item"><span class="nav-no">4</span> Keterlambatan Pengembalian</a>
        <a href="#halaman5" class="nav-item"><span class="nav-no">5</span> Rincian Transaksi</a>
      </div>
    </div>

    <a href="#nav-laporan" class="tombol-kembali no-print" title="Kembali ke menu navigasi">
      ⬆ Kembali ke Menu
    </a>

    <div id="area-cetak">

      <!-- ===================== HALAMAN 1 ===================== -->
      <div class="kertas" id="halaman1">
        <div class="laporan-kop">
          <h2>LAPORAN KEGIATAN PERPUSTAKAAN SEKOLAH DIGITAL</h2>
          <p>Periode: <?= tanggalIndo($tanggalAwal) ?> s.d. <?= tanggalIndo($tanggalAkhir) ?></p>
          <p>Dicetak pada: <?= tanggalIndo(date('Y-m-d')) ?></p>
          <hr>
        </div>

        <h2>1. Kondisi Perpustakaan Saat Ini</h2>
        <table style="margin-bottom:24px;">
          <tr><th>Keterangan</th><th>Jumlah</th></tr>
          <tr><td>Jumlah Judul Buku</td><td><?= $totalBukuJudul ?> judul</td></tr>
          <tr><td>Jumlah Total Eksemplar Buku</td><td><?= $totalEksemplar ?> eksemplar</td></tr>
          <tr><td>Jumlah Anggota Aktif</td><td><?= $totalAnggotaAktif ?> siswa</td></tr>
          <tr><td>Buku Sedang Dipinjam (saat ini)</td><td><?= $sedangDipinjamSkrg ?> eksemplar</td></tr>
        </table>

        <a href="#nav-laporan" class="kembali-halaman no-print">↑ Kembali ke Menu</a>
        <div class="no-halaman">Halaman 1</div>
      </div>

      <!-- ===================== HALAMAN 2 ===================== -->
      <div class="kertas" id="halaman2">
        <h2>2. Ringkasan Transaksi Periode Ini</h2>
        <table style="margin-bottom:24px;">
          <tr><th>Keterangan</th><th>Jumlah</th></tr>
          <tr><td>Total Peminjaman Baru</td><td><?= $totalTransaksiPeriode ?> transaksi</td></tr>
          <tr><td>Pengembalian Tepat Waktu</td><td><?= $kembaliTepatWaktu ?> transaksi</td></tr>
          <tr><td>Pengembalian Terlambat</td><td><?= $kembaliTerlambat ?> transaksi</td></tr>
          <tr><td>Jumlah Perpanjangan Peminjaman</td><td><?= $totalPerpanjanganPeriode ?> kali</td></tr>
          <tr><td>Total Denda (periode ini)</td><td><?= rupiah($totalDendaPeriode) ?></td></tr>
          <tr><td>&nbsp;&nbsp;— Denda Sudah Dibayar</td><td><?= rupiah($dendaLunasPeriode) ?></td></tr>
          <tr><td>&nbsp;&nbsp;— Denda Belum Dibayar</td><td><?= rupiah($dendaBelumPeriode) ?></td></tr>
        </table>

        <a href="#nav-laporan" class="kembali-halaman no-print">↑ Kembali ke Menu</a>
        <div class="no-halaman">Halaman 2</div>
      </div>

      <!-- ===================== HALAMAN 3 ===================== -->
      <div class="kertas" id="halaman3">
        <h2>3. Buku Terpopuler (Periode Ini)</h2>
        <table style="margin-bottom:24px;">
          <tr><th>No</th><th>Judul</th><th>Pengarang</th><th>Jumlah Dipinjam</th></tr>
          <?php if ($bukuTerpopuler->num_rows === 0): ?>
            <tr><td colspan="4" class="text-center">Belum ada transaksi pada periode ini.</td></tr>
          <?php endif; ?>
          <?php $no=1; while ($b = $bukuTerpopuler->fetch_assoc()): ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($b['judul']) ?></td>
            <td><?= htmlspecialchars($b['pengarang']) ?></td>
            <td><?= $b['jumlah_pinjam'] ?>x</td>
          </tr>
          <?php endwhile; ?>
        </table>

        <a href="#nav-laporan" class="kembali-halaman no-print">↑ Kembali ke Menu</a>
        <div class="no-halaman">Halaman 3</div>
      </div>

      <!-- ===================== HALAMAN 4 ===================== -->
      <div class="kertas" id="halaman4">
        <h2>4. Laporan Keterlambatan Pengembalian</h2>
        <p style="font-size:12.5px; color:#374151; margin-top:-6px; margin-bottom:10px;">
          Total item terlambat: <b><?= $jumlahItemTerlambat ?></b> transaksi
          &nbsp;|&nbsp; Denda dihitung Rp<?= number_format(DENDA_PER_HARI,0,',','.') ?> / hari keterlambatan
          <?php if ($jumlahItemTerlambat > 0): ?>
            &nbsp;|&nbsp; Halaman <?= $halTerlambat ?> dari <?= $totalHalTerlambat ?>
          <?php endif; ?>
        </p>
        <div style="overflow-x:auto;">
        <table style="margin-bottom:4px; font-size:11.5px; min-width:100%;">
          <tr>
            <th>No</th>
            <th>Kode Buku</th>
            <th>Judul Buku</th>
            <th>Peminjam</th>
            <th>Kelas</th>
            <th>Tgl Pinjam</th>
            <th>Tgl Kembali</th>
            <th>Keterlambatan</th>
            <th>Jumlah Denda</th>
            <th>Status Bayar</th>
          </tr>
          <?php if ($jumlahItemTerlambat === 0): ?>
            <tr><td colspan="10" class="text-center">Tidak ada keterlambatan pengembalian pada periode ini. 👍</td></tr>
          <?php endif; ?>
          <?php
            $noTerlambat = $offsetTerlambat + 1;
            $subtotalDenda = 0;
            while ($k = $keterlambatan->fetch_assoc()):
              $hariTerlambat = $k['jumlah_hari_terlambat'] > 0 ? $k['jumlah_hari_terlambat'] : $k['hari_terlambat_dihitung'];
              $belumKembali = $k['status'] === 'Dipinjam';
              $dendaItem = $k['denda'] > 0 ? (float) $k['denda'] : (DENDA_PER_HARI * max(0, $hariTerlambat));
              $subtotalDenda += $dendaItem;
          ?>
          <tr>
            <td><?= $noTerlambat++ ?></td>
            <td><?= htmlspecialchars($k['kode_buku']) ?></td>
            <td><?= htmlspecialchars($k['judul']) ?></td>
            <td><?= htmlspecialchars($k['nama_anggota']) ?></td>
            <td><?= htmlspecialchars($k['kelas']) ?></td>
            <td><?= tanggalIndo($k['tanggal_pinjam']) ?></td>
            <td><?= $belumKembali ? '<span class="badge badge-red">Belum Kembali</span>' : tanggalIndo($k['tanggal_kembali']) ?></td>
            <td style="text-align:center;"><?= (int) $hariTerlambat ?> hari</td>
            <td style="text-align:right;"><?= rupiah($dendaItem) ?><?= $k['denda'] > 0 ? '' : ' *' ?></td>
            <td><?= $k['denda_dibayar'] === 'Sudah' ? '<span class="badge badge-green">Lunas</span>' : '<span class="badge badge-red">Belum</span>' ?></td>
          </tr>
          <?php endwhile; ?>
          <?php if ($jumlahItemTerlambat > 0): ?>
          <tr>
            <td colspan="8" style="text-align:right; font-weight:700; background:#eef0f3;">Total Denda (halaman ini)&nbsp;:</td>
            <td style="text-align:right; font-weight:700; background:#eef0f3;"><?= rupiah($subtotalDenda) ?></td>
            <td style="background:#eef0f3;"></td>
          </tr>
          <tr>
            <td colspan="8" style="text-align:right; font-weight:700; background:#dbeafe;">Total Denda Keseluruhan Periode&nbsp;:</td>
            <td style="text-align:right; font-weight:700; background:#dbeafe;"><?= rupiah($totalDendaKeterlambatan) ?></td>
            <td style="background:#dbeafe;"></td>
          </tr>
          <?php endif; ?>
        </table>
        </div>
        <?php if ($jumlahItemTerlambat > 0): ?>
        <p style="font-size:10.5px; color:#6b7280; margin-top:0;">* Denda estimasi (belum tercatat final di sistem, dihitung otomatis dari jumlah hari terlambat).</p>
        <?php endif; ?>

        <?php renderPagination('hal_terlambat', $halTerlambat, $totalHalTerlambat, 'halaman4'); ?>

        <a href="#nav-laporan" class="kembali-halaman no-print">↑ Kembali ke Menu</a>
        <div class="no-halaman">Halaman 4</div>
      </div>

      <!-- ===================== HALAMAN 5 (dst. mengikuti jumlah baris) ===================== -->
      <div class="kertas" id="halaman5">
        <h2>5. Rincian Transaksi Periode Ini</h2>
        <?php if ($totalDaftarTransaksi > 0): ?>
        <p style="font-size:12.5px; color:#374151; margin-top:-6px; margin-bottom:10px;">
          Total transaksi: <b><?= $totalDaftarTransaksi ?></b>
          &nbsp;|&nbsp; Halaman <?= $halTransaksi ?> dari <?= $totalHalTransaksi ?>
        </p>
        <?php endif; ?>
        <div style="overflow-x:auto;">
        <table style="font-size:11.5px; min-width:100%;">
          <tr><th>Kode</th><th>Siswa</th><th>Buku</th><th>Pinjam</th><th>Jatuh Tempo</th><th>Kembali</th><th>Status</th><th>Denda</th></tr>
          <?php if ($daftarTransaksi->num_rows === 0): ?>
            <tr><td colspan="8" class="text-center">Tidak ada transaksi pada periode ini.</td></tr>
          <?php endif; ?>
          <?php while ($r = $daftarTransaksi->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($r['kode_transaksi']) ?></td>
            <td><?= htmlspecialchars($r['nama_anggota']) ?> (<?= htmlspecialchars($r['kelas']) ?>)</td>
            <td><?= htmlspecialchars($r['judul']) ?></td>
            <td><?= tanggalIndo($r['tanggal_pinjam']) ?></td>
            <td><?= tanggalIndo($r['tanggal_jatuh_tempo']) ?></td>
            <td><?= tanggalIndo($r['tanggal_kembali']) ?></td>
            <td><?= badgeStatusLaporan($r['status']) ?></td>
            <td><?= $r['denda'] > 0 ? rupiah($r['denda']) : '-' ?></td>
          </tr>
          <?php endwhile; ?>
        </table>
        </div>

        <?php renderPagination('hal_transaksi', $halTransaksi, $totalHalTransaksi, 'halaman5'); ?>

        <div class="ttd-area">
          <div class="ttd-box">
            Mengetahui,<br>Kepala Sekolah
            <div class="garis">( .............................. )</div>
          </div>
          <div class="ttd-box">
            <?= tanggalIndo(date('Y-m-d')) ?><br>Petugas Perpustakaan
            <div class="garis"><?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
          </div>
        </div>

        <a href="#nav-laporan" class="kembali-halaman no-print">↑ Kembali ke Menu</a>
        <div class="no-halaman">Halaman 5</div>
      </div>

    </div>
  </div>
</div>
</body>
</html>