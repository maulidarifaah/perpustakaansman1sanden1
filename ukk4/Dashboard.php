<?php
require_once '../config/db.php';
require_once '../config/Functions.php';
require_once 'Auth.php';
$active = 'Dashboard';

// 1. Data Statistik Utama
$totalBuku = $koneksi->query("SELECT COALESCE(SUM(stok_total),0) t FROM buku")->fetch_assoc()['t'];
$totalAnggota = $koneksi->query("SELECT COUNT(*) t FROM anggota WHERE status='Aktif'")->fetch_assoc()['t'];
$sedangDipinjam = $koneksi->query("SELECT COUNT(*) t FROM peminjaman WHERE status='Dipinjam'")->fetch_assoc()['t'];
$terlambatAktif = $koneksi->query("SELECT COUNT(*) t FROM peminjaman WHERE status='Dipinjam' AND tanggal_jatuh_tempo < CURDATE()")->fetch_assoc()['t'];
$totalDenda = $koneksi->query("SELECT COALESCE(SUM(denda),0) t FROM peminjaman WHERE denda_dibayar='Belum'")->fetch_assoc()['t'];

// 2. Data untuk Diagram 1: Riwayat Peminjaman (6 Bulan Terakhir)
$blnLabels = [];
$blnData   = [];
for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $namaBulan = date('M Y', strtotime("-$i months"));
    $count = $koneksi->query("SELECT COUNT(*) t FROM peminjaman WHERE DATE_FORMAT(tanggal_pinjam, '%Y-%m') = '$date'")->fetch_assoc()['t'];
    
    $blnLabels[] = $namaBulan;
    $blnData[]   = $count;
}

// 3. Data untuk Diagram 2: Jumlah Anggota (Aktif vs Nonaktif)
$anggotaAktif = $totalAnggota;
$anggotaNonAktif = $koneksi->query("SELECT COUNT(*) t FROM anggota WHERE status != 'Aktif'")->fetch_assoc()['t'];

// 4. Data untuk Diagram 3: Jumlah Buku (Tersedia vs Dipinjam)
$bukuDipinjam = $sedangDipinjam;
$bukuTersedia = $totalBuku - $bukuDipinjam;
if ($bukuTersedia < 0) $bukuTersedia = 0;

// 5. Data Transaksi Terbaru
$transaksiTerbaru = $koneksi->query("
    SELECT p.*, a.nama AS nama_anggota, b.judul
    FROM peminjaman p
    JOIN anggota a ON a.id_anggota = p.id_anggota
    JOIN buku b ON b.id_buku = p.id_buku
    ORDER BY p.id_peminjaman DESC LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - Perpustakaan Digital</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin-theme.css">

<!-- CDN Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
  body { background: #F5EFDE !important; }
  /* Header & Topbar */
  .topbar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .topbar-brand img.logo-sekolah {
    height: 40px;
    width: auto;
    object-fit: contain;
  }
  .topbar-brand h1 {
    margin: 0;
    font-size: 22px;
  }

  /* Grid Kartu Statistik Sesuai Gambar */
  .cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
  }
  .card-stat-custom {
    background: #FFFCF3;
    border-radius: 12px;
    padding: 20px 24px;
    box-shadow: 0 10px 24px -14px rgba(18, 40, 31, 0.25);
    border-left: 6px solid #1F4032;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .card-stat-custom.blue   { border-left-color: #1F4032; }
  .card-stat-custom.green  { border-left-color: #B8863A; }
  .card-stat-custom.orange { border-left-color: #12281F; }
  .card-stat-custom.red    { border-left-color: #A63325; }

  .card-stat-custom h3 {
    margin: 0 0 6px 0;
    font-size: 28px;
    font-weight: 700;
    color: #12281F;
  }
  .card-stat-custom p {
    margin: 0;
    font-size: 14px;
    color: #5B5348;
    line-height: 1.3;
  }

  /* Layout 3 Diagram */
  .charts-grid-3 {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
  }
  .chart-card {
    background: #FFFCF3;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 10px 24px -14px rgba(18, 40, 31, 0.2);
  }
  .chart-card h3 {
    margin-top: 0;
    margin-bottom: 16px;
    font-size: 15px;
    color: #374151;
    text-align: center;
  }
  .chart-container {
    position: relative;
    height: 230px;
    width: 100%;
  }

  @media (max-width: 1100px) {
    .charts-grid-3 {
      grid-template-columns: 1fr;
    }
  }
</style>
</head>
<body>
<div class="app">
  <?php include 'sidebar.php'; ?>
  
  <div class="main">
    <!-- Topbar -->
    <div class="topbar">
      <div class="topbar-brand">
        <img src="../assets/img/logo sma.jpeg" alt="Logo Sekolah" class="logo-sekolah">
        <h1>Dashboard Admin</h1>
      </div>
      <div style="display:flex; align-items:center;">
        <?php include 'Notifikasi.php'; ?>
        <div class="user-chip">👤 <?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
      </div>
    </div>

    <!-- Cards Stat (Tampilan Presisi Seperti Gambar) -->
    <div class="cards-grid">
      <div class="card-stat-custom blue">
        <h3><?= $totalBuku ?></h3>
        <p>Total Eksemplar Buku</p>
      </div>
      <div class="card-stat-custom green">
        <h3><?= $totalAnggota ?></h3>
        <p>Anggota Aktif</p>
      </div>
      <div class="card-stat-custom orange">
        <h3><?= $sedangDipinjam ?></h3>
        <p>Buku Sedang Dipinjam</p>
      </div>
      <div class="card-stat-custom red">
        <h3><?= $terlambatAktif ?></h3>
        <p>Peminjaman Terlambat (belum kembali)</p>
      </div>
    </div>

    <!-- 3 Area Diagram / Grafik -->
    <div class="charts-grid-3">
      <!-- 1. Diagram Riwayat Peminjaman -->
      <div class="chart-card">
        <h3>📊 Riwayat Peminjaman (6 Bulan)</h3>
        <div class="chart-container">
          <canvas id="chartRiwayat"></canvas>
        </div>
      </div>

      <!-- 2. Diagram Jumlah Anggota -->
      <div class="chart-card">
        <h3>👥 Data Anggota</h3>
        <div class="chart-container">
          <canvas id="chartAnggota"></canvas>
        </div>
      </div>

      <!-- 3. Diagram Jumlah Buku -->
      <div class="chart-card">
        <h3>📚 Data Buku</h3>
        <div class="chart-container">
          <canvas id="chartBuku"></canvas>
        </div>
      </div>
    </div>

    <!-- Tabel Transaksi Terbaru -->
    <div class="panel">
      <h2>Transaksi Terbaru</h2>
      <table>
        <tr><th>Kode</th><th>Anggota</th><th>Buku</th><th>Tgl Pinjam</th><th>Jatuh Tempo</th><th>Status</th><th>Denda</th></tr>
        <?php while ($row = $transaksiTerbaru->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['kode_transaksi']) ?></td>
          <td><?= htmlspecialchars($row['nama_anggota']) ?></td>
          <td><?= htmlspecialchars($row['judul']) ?></td>
          <td><?= tanggalIndo($row['tanggal_pinjam']) ?></td>
          <td><?= tanggalIndo($row['tanggal_jatuh_tempo']) ?></td>
          <td><?= badgeStatus($row['status']) ?></td>
          <td><?= $row['denda'] > 0 ? rupiah($row['denda']) : '-' ?></td>
        </tr>
        <?php endwhile; ?>
      </table>
    </div>

  </div>
</div>

<!-- Render JavaScript Chart.js -->
<script>
  // 1. Chart Riwayat Peminjaman (Bar Chart)
  new Chart(document.getElementById('chartRiwayat').getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($blnLabels) ?>,
      datasets: [{
        label: 'Jumlah Transaksi',
        data: <?= json_encode($blnData) ?>,
        backgroundColor: '#1F4032',
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
  });

  // 2. Chart Anggota (Doughnut Chart)
  new Chart(document.getElementById('chartAnggota').getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: ['Aktif', 'Nonaktif'],
      datasets: [{
        data: [<?= $anggotaAktif ?>, <?= $anggotaNonAktif ?>],
        backgroundColor: ['#1F4032', '#B8863A']
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } }
    }
  });

  // 3. Chart Buku (Pie Chart)
  new Chart(document.getElementById('chartBuku').getContext('2d'), {
    type: 'pie',
    data: {
      labels: ['Tersedia', 'Dipinjam'],
      datasets: [{
        data: [<?= $bukuTersedia ?>, <?= $bukuDipinjam ?>],
        backgroundColor: ['#A63325', '#B8863A']
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } }
    }
  });
</script>
</body>
</html>