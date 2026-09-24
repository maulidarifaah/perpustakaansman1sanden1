<?php
/**
 * Komponen Notifikasi Peminjaman untuk Admin
 * Menampilkan lonceng + badge jumlah peminjaman yang terjadi hari ini.
 * Include file ini di topbar setiap halaman admin (setelah $koneksi tersedia).
 */
$notifPeminjaman = $koneksi->query("
    SELECT p.id_peminjaman, p.kode_transaksi, a.nama AS nama_anggota, a.kelas, b.judul
    FROM peminjaman p
    JOIN anggota a ON a.id_anggota = p.id_anggota
    JOIN buku b ON b.id_buku = p.id_buku
    WHERE p.tanggal_pinjam = CURDATE()
    ORDER BY p.id_peminjaman DESC
");
$notifList = [];
while ($n = $notifPeminjaman->fetch_assoc()) {
    $notifList[] = $n;
}
$notifCount = count($notifList);
// ID peminjaman terbaru hari ini, dipakai di JS untuk mendeteksi
// apakah ada peminjaman baru yang belum "didengar" (lihat localStorage di script bawah)
$notifLastPeminjamanId = $notifCount > 0 ? (int) $notifList[0]['id_peminjaman'] : 0;

$notifBooking = $koneksi->query("
    SELECT bk.id_booking, bk.kode_booking, a.nama AS nama_anggota, a.kelas, b.judul
    FROM booking bk
    JOIN anggota a ON a.id_anggota = bk.id_anggota
    JOIN buku b ON b.id_buku = bk.id_buku
    WHERE bk.status = 'Menunggu'
    ORDER BY bk.id_booking DESC
");
$notifBookingList = [];
while ($nb = $notifBooking->fetch_assoc()) {
    $notifBookingList[] = $nb;
}
$notifBookingCount = count($notifBookingList);

$notifTotal = $notifCount + $notifBookingCount;
?>
<div class="notif-bell" id="notifBell">
  <button type="button" class="notif-btn" onclick="toggleNotif()" title="Notifikasi">
    🔔
    <?php if ($notifTotal > 0): ?>
      <span class="notif-badge"><?= $notifTotal ?></span>
    <?php endif; ?>
  </button>
  <div class="notif-dropdown" id="notifDropdown">
    <div class="notif-header">Peminjaman Hari Ini (<?= $notifCount ?>)</div>
    <?php if ($notifCount === 0): ?>
      <div class="notif-empty">Belum ada peminjaman hari ini.</div>
    <?php else: ?>
      <?php foreach ($notifList as $n): ?>
        <a href="transaksi.php" class="notif-item">
          <div class="notif-item-title"><?= htmlspecialchars($n['nama_anggota']) ?> <small>(<?= htmlspecialchars($n['kelas']) ?>)</small></div>
          <div class="notif-item-sub">Meminjam: <?= htmlspecialchars($n['judul']) ?></div>
          <div class="notif-item-code"><?= htmlspecialchars($n['kode_transaksi']) ?></div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="notif-header">Booking Menunggu Diproses (<?= $notifBookingCount ?>)</div>
    <?php if ($notifBookingCount === 0): ?>
      <div class="notif-empty">Tidak ada booking menunggu.</div>
    <?php else: ?>
      <?php foreach ($notifBookingList as $nb): ?>
        <a href="booking.php" class="notif-item">
          <div class="notif-item-title"><?= htmlspecialchars($nb['nama_anggota']) ?> <small>(<?= htmlspecialchars($nb['kelas']) ?>)</small></div>
          <div class="notif-item-sub">Booking: <?= htmlspecialchars($nb['judul']) ?></div>
          <div class="notif-item-code"><?= htmlspecialchars($nb['kode_booking']) ?></div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Bunyi notifikasi untuk peminjaman baru (beep pendek, tanpa file eksternal) -->
<audio id="notifSound" preload="auto">
  <source src="data:audio/wav;base64,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" type="audio/wav">
</audio>

<style>
.notif-bell { position: relative; margin-right: 14px; }
.notif-btn { position: relative; background: none; border: none; font-size: 20px; cursor: pointer; padding: 6px; line-height: 1; }
.notif-badge {
  position: absolute; top: -2px; right: -2px;
  background: #dc2626; color: #fff; font-size: 11px; font-weight: 700;
  padding: 1px 5px; border-radius: 10px; min-width: 16px; text-align: center;
}
.notif-dropdown {
  display: none; position: absolute; right: 0; top: 38px; width: 300px;
  max-height: 360px; overflow-y: auto; background: #fff; border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.15); z-index: 999;
}
.notif-dropdown.show { display: block; }
.notif-header {
  padding: 12px 14px; font-weight: 700; font-size: 14px;
  border-bottom: 1px solid #eee; color: #111827;
}
.notif-empty { padding: 16px; font-size: 13px; color: #6b7280; text-align: center; }
.notif-item {
  display: block; padding: 10px 14px; border-bottom: 1px solid #f3f4f6;
  text-decoration: none; color: inherit;
}
.notif-item:hover { background: #f9fafb; }
.notif-item-title { font-size: 13.5px; font-weight: 600; color: #111827; }
.notif-item-title small { font-weight: 400; color: #6b7280; }
.notif-item-sub { font-size: 12.5px; color: #374151; margin-top: 2px; }
.notif-item-code { font-size: 11px; color: #9ca3af; margin-top: 2px; }
</style>

<script>
function toggleNotif() {
  document.getElementById('notifDropdown').classList.toggle('show');
}
document.addEventListener('click', function (e) {
  var bell = document.getElementById('notifBell');
  if (bell && !bell.contains(e.target)) {
    document.getElementById('notifDropdown').classList.remove('show');
  }
});

// ---- Bunyi notifikasi saat menerima peminjaman baru ----
(function () {
  // ID peminjaman terbaru hari ini menurut server (0 jika belum ada peminjaman hari ini)
  var idPeminjamanTerbaru = <?= (int) $notifLastPeminjamanId ?>;
  var KEY = 'lastDengarPeminjamanId';

  if (idPeminjamanTerbaru > 0) {
    var idTerakhirDidengar = parseInt(localStorage.getItem(KEY) || '0', 10);

    if (idPeminjamanTerbaru > idTerakhirDidengar) {
      var audio = document.getElementById('notifSound');
      if (audio) {
        // Sebagian browser memblokir autoplay suara tanpa interaksi user;
        // kegagalan putar diabaikan diam-diam agar tidak mengganggu halaman
        audio.play().catch(function () {});
      }
    }

    localStorage.setItem(KEY, idPeminjamanTerbaru);
  }
})();
</script>