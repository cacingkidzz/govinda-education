<?php
declare(strict_types=1);

/**
 * Modal "Pertemuan Manual".
 *
 * Dipakai bersama oleh halaman daftar realisasi dan rekap per kelompok.
 * Variabel yang perlu tersedia sebelum include:
 * - $manualRelations  : hasil GroupSubject::activeOptions()
 * - $manualReturnFrom : rentang tanggal halaman asal ("dari|sampai"), opsional
 * - $manualReturnTo   : "rekap" bila halaman asal rekap per kelompok, opsional
 */

$manualRelations = $manualRelations ?? [];
$manualReturnFrom = $manualReturnFrom ?? '';
$manualReturnTo = $manualReturnTo ?? '';

$manualGrouped = [];
foreach ($manualRelations as $manualRelation) {
    $manualGrouped[(string) $manualRelation['group_name']][] = $manualRelation;
}
?>
<div class="modal fade" id="manualMeetingModal" tabindex="-1" aria-labelledby="manualMeetingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="get" action="<?= e(url('admin/realisasi-pertemuan/catat.php')) ?>" class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="manualMeetingModalLabel">
                        <i class="bi bi-journal-plus me-1"></i>Pertemuan Manual
                    </h2>
                    <div class="text-secondary small">
                        Catat pertemuan tanpa terikat jadwal tetap.
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="manual" value="1">
                <input type="hidden" name="return_from" value="<?= e($manualReturnFrom) ?>">
                <input type="hidden" name="return_to" value="<?= e($manualReturnTo) ?>">

                <?php if ($manualRelations === []): ?>
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Belum ada mata pelajaran aktif pada group mana pun. Tambahkan relasi
                        mata pelajaran–guru terlebih dahulu.
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <label for="manual_group_subject_id" class="form-label">
                            Group &amp; mata pelajaran <span class="text-danger">*</span>
                        </label>
                        <select class="form-select js-select2" id="manual_group_subject_id"
                                name="group_subject_id" required data-placeholder="Pilih group dan mata pelajaran">
                            <option value="">Pilih group dan mata pelajaran</option>
                            <?php foreach ($manualGrouped as $groupName => $items): ?>
                                <optgroup label="<?= e($groupName) ?>">
                                    <?php foreach ($items as $relation): ?>
                                        <option value="<?= (int) $relation['id'] ?>">
                                            <?= e($relation['subject_name']) ?> — <?= e($relation['teacher_name']) ?>
                                            (<?= e($relation['group_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Guru mengikuti guru pengampu pada relasi mata pelajaran.</div>
                    </div>
                    <div>
                        <label for="manual_tanggal" class="form-label">
                            Tanggal pertemuan <span class="text-danger">*</span>
                        </label>
                        <input type="date" class="form-control" id="manual_tanggal" name="tanggal"
                               value="<?= e(date('Y-m-d')) ?>" required>
                        <div class="form-text">
                            Boleh lebih dari satu pertemuan pada hari yang sama; jam diatur pada langkah berikutnya.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" <?= $manualRelations === [] ? 'disabled' : '' ?>>
                    <i class="bi bi-arrow-right-circle me-1"></i>Lanjut Catat
                </button>
            </div>
        </form>
    </div>
</div>
