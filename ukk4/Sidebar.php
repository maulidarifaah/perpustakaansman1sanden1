<style>
  /* Tema sidebar disamakan dengan landing page (index.php): krem/paper + aksen merah cap */
  .sidebar {
    background: #F5EFDE !important;
    border-right: 1px solid rgba(36, 31, 26, 0.14);
  }
  .sidebar-brand span { color: #12281F !important; }
  .sidebar a {
    display: block;
    color: #5B5348 !important;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    text-decoration: none;
    padding: 12px 16px;
    transition: background 0.15s ease, color 0.15s ease;
  }
  .sidebar a:hover {
    background: rgba(36, 31, 26, 0.06) !important;
    color: #241F1A !important;
  }
  .sidebar a.active {
    background: #A63325 !important;
    color: #FFF6EE !important;
    font-weight: 600;
  }
  .sidebar .logout { border-top: 1px solid rgba(36, 31, 26, 0.14); margin-top: 12px; padding-top: 12px; }
  .sidebar .logout a { color: #A63325 !important; }
  .sidebar .logout a:hover {
    background: rgba(166, 51, 37, 0.1) !important;
    color: #8A2A1E !important;
  }
</style>
<div class="sidebar">
  <!-- Elemen Brand/Logo menggantikan h2 biasa -->
  <div class="sidebar-brand" style="padding: 0 16px 20px 16px; display: flex; align-items: center; justify-content: center;">
    <img src="../assets/img/logo sma.jpeg" alt="Logo" style="height: 35px; width: auto; margin-right: 8px; border-radius: 4px;">
    <span style="font-weight: bold; font-size: 16px; color: #12281F;">Admin Perpustakaan</span>
  </div>

  <a href="dashboard.php" class="<?= $active==='Dashboard'?'active':'' ?>">🏠 Dashboard</a>
  <a href="buku.php" class="<?= $active==='buku'?'active':'' ?>">📖 Kelola Data Buku</a>
  <a href="anggota.php" class="<?= $active==='anggota'?'active':'' ?>">👥 Kelola Anggota</a>
  <a href="petugas.php" class="<?= $active==='Petugas'?'active':'' ?>">🧑‍💼 Kelola Petugas</a>
  <a href="admin.php" class="<?= $active==='Admin'?'active':'' ?>">🛡️ Kelola Admin</a>
  <a href="transaksi.php" class="<?= $active==='transaksi'?'active':'' ?>">🔄 Transaksi Peminjaman</a>
  <a href="booking.php" class="<?= $active==='Booking'?'active':'' ?>">🔖 Kelola Booking</a>
  <a href="laporan.php" class="<?= $active==='laporan'?'active':'' ?>">🖨️ Laporan Kepala Sekolah</a>

  <div class="logout">
    <a href="logout.php">🚪 Logout</a>
  </div>
</div>