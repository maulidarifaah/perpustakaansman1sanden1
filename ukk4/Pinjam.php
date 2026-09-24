<?php
require_once '../config/db.php';
require_once '../config/Functions.php';
require_once 'AuthAdmin.php';

$msg = '';
$msgType = 'success';

$lamaPinjam = defined('LAMA_PINJAM_HARI')
    ? LAMA_PINJAM_HARI
    : 7;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idAnggota = (int) ($_POST['id_anggota'] ?? 0);
    $idBuku = (int) ($_POST['id_buku'] ?? 0);

    if ($idAnggota <= 0 || $idBuku <= 0) {
        $msg = 'Siswa dan buku wajib dipilih.';
        $msgType = 'error';
    } else {
        $stmt = $koneksi->prepare(
            "SELECT COUNT(*) AS total
             FROM peminjaman
             WHERE id_anggota = ?
             AND status = 'Dipinjam'"
        );
        $stmt->bind_param('i', $idAnggota);
        $stmt->execute();

        $aktif = $stmt->get_result()->fetch_assoc()['total'];

        $stmtBuku = $koneksi->prepare(
            "SELECT stok_tersedia
             FROM buku
             WHERE id_buku = ?"
        );
        $stmtBuku->bind_param('i', $idBuku);
        $stmtBuku->execute();

        $buku = $stmtBuku->get_result()->fetch_assoc();

        if ($aktif >= 3) {
            $msg = 'Siswa sudah meminjam maksimal 3 buku.';
            $msgType = 'error';
        } elseif (!$buku || $buku['stok_tersedia'] < 1) {
            $msg = 'Stok buku sedang tidak tersedia.';
            $msgType = 'error';
        } else {
            $kode = buatKodeTransaksi();
            $tanggalPinjam = date('Y-m-d');
            $tanggalJatuhTempo = date(
                'Y-m-d',
                strtotime("+{$lamaPinjam} days")
            );

            $stmtSimpan = $koneksi->prepare(
                "INSERT INTO peminjaman
                (
                    kode_transaksi,
                    id_anggota,
                    id_buku,
                    tanggal_pinjam,
                    tanggal_jatuh_tempo,
                    status
                )
                VALUES (?, ?, ?, ?, ?, 'Dipinjam')"
            );

            $stmtSimpan->bind_param(
                'siiss',
                $kode,
                $idAnggota,
                $idBuku,
                $tanggalPinjam,
                $tanggalJatuhTempo
            );

            if ($stmtSimpan->execute()) {
                $stmtStok = $koneksi->prepare(
                    "UPDATE buku
                     SET stok_tersedia = stok_tersedia - 1
                     WHERE id_buku = ?"
                );
                $stmtStok->bind_param('i', $idBuku);
                $stmtStok->execute();

                $msg = 'Peminjaman berhasil. Kode transaksi: ' . $kode;
            } else {
                $msg = 'Peminjaman gagal disimpan.';
                $msgType = 'error';
            }
        }
    }
}






$dataAnggota = $koneksi->query(
    "SELECT id_anggota, nis, nama
     FROM anggota
     ORDER BY nama ASC"
);

$dataBuku = $koneksi->query(
    "SELECT id_buku, kode_buku, judul, stok_tersedia
     FROM buku
     WHERE stok_tersedia > 0
     ORDER BY judul ASC"
);
?>


<?php if ($msg): ?>
    <div class="alert <?= $msgType === 'error'
        ? 'alert-error'
        : 'alert-success' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>Form Peminjaman Buku</h2>

    <form method="POST">
        <div class="form-group">
            <label>Pilih Siswa</label>
            <select name="id_anggota" required>
                <option value="">-- Pilih Siswa --</option>

                <?php while ($anggota = $dataAnggota->fetch_assoc()): ?>
                    <option value="<?= $anggota['id_anggota'] ?>">
                        <?= htmlspecialchars($anggota['nis']) ?>
                        -
                        <?= htmlspecialchars($anggota['nama']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Pilih Buku</label>
            <select name="id_buku" required>
                <option value="">-- Pilih Buku --</option>

                <?php while ($buku = $dataBuku->fetch_assoc()): ?>
                    <option value="<?= $buku['id_buku'] ?>">
                        <?= htmlspecialchars($buku['kode_buku']) ?>
                        -
                        <?= htmlspecialchars($buku['judul']) ?>
                        (Stok: <?= $buku['stok_tersedia'] ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-green">
            Proses Peminjaman
        </button>
    </form>
</div>