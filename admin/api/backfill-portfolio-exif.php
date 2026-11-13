<?php
// admin/api/backfill-portfolio-exif.php
// Backfill EXIF into portfolio rows that have no EXIF yet.
// Strategy:
//   1. Try pure-PHP EXIF reader on the portfolio file itself (works if file was
//      moved without GD processing, i.e. landscape shots with correct orientation).
//   2. If that yields nothing, match by filesize against all gallery files and
//      try reading EXIF from the gallery file.
//   3. If gallery_photos DB already has EXIF, use that directly (fastest).
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/exif_reader.php';

$db = getDB();
$updated = 0;
$skipped = 0;
$errors  = [];

$portDir    = realpath(__DIR__ . '/../../assets/portfolio/') . '/';
$galBase    = realpath(__DIR__ . '/../../gallery/assets/')   . '/';

// Get all portfolio rows that still have no EXIF
$rows = $db->query(
    "SELECT id, filename FROM portfolio
     WHERE focal_length IS NULL AND aperture IS NULL AND iso IS NULL
     ORDER BY created_at DESC"
)->fetchAll();

// Pre-load all gallery_photos EXIF from DB (keyed by filesize for matching)
$gpBySize = [];
try {
    $gpRows = $db->query(
        "SELECT gp.filename AS gp_file, gp.focal_length, gp.aperture, gp.iso,
                gp.shutter_speed, gp.camera_model, g.folder
         FROM gallery_photos gp
         JOIN galleries g ON g.id = gp.gallery_id
         WHERE gp.focal_length IS NOT NULL OR gp.iso IS NOT NULL"
    )->fetchAll();
    foreach ($gpRows as $gp) {
        $path = $galBase . $gp['folder'] . '/' . $gp['gp_file'];
        if (is_file($path)) {
            $sz = filesize($path);
            $gpBySize[$sz][] = $gp;
        }
    }
} catch (Exception $e) {}

foreach ($rows as $row) {
    $filePath = $portDir . $row['filename'];
    if (!is_file($filePath)) { $skipped++; continue; }

    $exif = pure_php_read_exif($filePath);

    // If file has no EXIF (GD stripped it), try matching by size to a gallery file
    if (!$exif['focal_length'] && !$exif['iso'] && !$exif['aperture']) {
        $sz = filesize($filePath);

        // First check gallery_photos DB (most reliable)
        if (!empty($gpBySize[$sz])) {
            $gp = $gpBySize[$sz][0];
            $exif['focal_length']  = $gp['focal_length'];
            $exif['aperture']      = $gp['aperture'];
            $exif['iso']           = $gp['iso'];
            $exif['shutter_speed'] = $gp['shutter_speed'];
            $exif['camera_model']  = $gp['camera_model'];
        } else {
            // Try reading EXIF directly from matched gallery file on disk
            if (!empty($gpBySize[$sz])) {
                $gp   = $gpBySize[$sz][0];
                $gPath = $galBase . $gp['folder'] . '/' . $gp['gp_file'];
                $exif  = pure_php_read_exif($gPath);
            }
        }
    }

    // Still nothing — skip this file
    if (!$exif['focal_length'] && !$exif['iso'] && !$exif['aperture'] && !$exif['shutter_speed']) {
        $skipped++;
        continue;
    }

    try {
        $upd = $db->prepare(
            "UPDATE portfolio
             SET focal_length=?, aperture=?, iso=?, shutter_speed=?, camera_model=?
             WHERE id=?"
        );
        $upd->execute([
            $exif['focal_length'], $exif['aperture'],
            $exif['iso'], $exif['shutter_speed'],
            $exif['camera_model'], $row['id']
        ]);
        $updated++;
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

echo json_encode([
    'success' => true,
    'updated' => $updated,
    'skipped' => $skipped,
    'errors'  => $errors,
    'message' => "Backfill complete: {$updated} updated, {$skipped} had no readable EXIF."
]);
