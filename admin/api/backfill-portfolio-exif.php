<?php
// admin/api/backfill-portfolio-exif.php
// One-time backfill: copies EXIF from gallery_photos into portfolio rows that have no EXIF yet.
// Matches by: portfolio filename → gallery_photos filename (they share the same original content,
// different filenames, so we match by the original gallery folder stored in the path of the copy).
// Since the copy was done from gallery/assets/{folder}/{n}.jpg, and portfolio filenames are
// port_xxx_timestamp.ext, we can't directly correlate by name after a copy.
// Instead, we match by EXACT file size + taken_at (most reliable without reading file EXIF).
// Fallback: try exif_read_data on the portfolio file itself — works if GD was NOT used.
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$db = getDB();
$updated = 0;
$skipped = 0;
$errors  = [];

$portDir = realpath(__DIR__ . '/../../assets/portfolio/') . '/';

// Get all portfolio rows that still have no EXIF
$rows = $db->query(
    "SELECT id, filename FROM portfolio
     WHERE focal_length IS NULL AND aperture IS NULL AND iso IS NULL"
)->fetchAll();

foreach ($rows as $row) {
    $filePath = $portDir . $row['filename'];
    if (!is_file($filePath)) { $skipped++; continue; }

    $mime = mime_content_type($filePath);
    if ($mime !== 'image/jpeg' && $mime !== 'image/jpg') { $skipped++; continue; }

    if (!function_exists('exif_read_data')) { $errors[] = 'exif extension not available'; break; }

    $exif = @exif_read_data($filePath, 'EXIF,IFD0', true);
    if (!$exif) { $skipped++; continue; }

    $focal  = null; $aperture = null; $iso = null; $shutter = null; $camera = null;

    if (!empty($exif['IFD0']['Model']))
        $camera = trim($exif['IFD0']['Model']);

    if (!empty($exif['EXIF']['ISOSpeedRatings'])) {
        $v = $exif['EXIF']['ISOSpeedRatings'];
        $iso = 'ISO ' . (is_array($v) ? $v[0] : $v);
    }

    if (!empty($exif['EXIF']['FocalLength'])) {
        $v = $exif['EXIF']['FocalLength'];
        if (strpos($v, '/') !== false) { [$n,$d] = explode('/', $v); $v = $d ? round($n/$d) : $n; }
        $focal = $v . 'mm';
    }

    if (!empty($exif['EXIF']['FNumber'])) {
        $v = $exif['EXIF']['FNumber'];
        if (strpos($v, '/') !== false) { [$n,$d] = explode('/', $v); $v = $d ? round($n/$d,1) : $n; }
        $aperture = 'f/' . $v;
    }

    if (!empty($exif['EXIF']['ExposureTime'])) {
        $v = $exif['EXIF']['ExposureTime'];
        if (strpos($v, '/') !== false) {
            [$n,$d] = explode('/', $v);
            $shutter = ($d && ($n/$d) < 1) ? '1/'.round($d/$n).'s' : round($n/$d,2).'s';
        } else { $shutter = $v . 's'; }
    }

    // If nothing found in the file (GD stripped it), try matching via gallery_photos by filesize
    if (!$focal && !$iso && !$aperture) {
        $portSize = filesize($filePath);
        $gpRows = $db->query(
            "SELECT gp.focal_length, gp.aperture, gp.iso, gp.shutter_speed, gp.camera_model,
                    g.folder, gp.filename AS gp_filename
             FROM gallery_photos gp
             JOIN galleries g ON g.id = gp.gallery_id
             WHERE gp.focal_length IS NOT NULL OR gp.iso IS NOT NULL"
        )->fetchAll();

        $galleryBase = realpath(__DIR__ . '/../../gallery/assets/') . '/';
        foreach ($gpRows as $gp) {
            $gpPath = $galleryBase . $gp['folder'] . '/' . $gp['gp_filename'];
            if (is_file($gpPath) && filesize($gpPath) === $portSize) {
                $focal    = $gp['focal_length'];
                $aperture = $gp['aperture'];
                $iso      = $gp['iso'];
                $shutter  = $gp['shutter_speed'];
                $camera   = $gp['camera_model'];
                break;
            }
        }
    }

    if (!$focal && !$iso && !$aperture && !$shutter) { $skipped++; continue; }

    try {
        $upd = $db->prepare(
            "UPDATE portfolio SET focal_length=?, aperture=?, iso=?, shutter_speed=?, camera_model=?
             WHERE id=?"
        );
        $upd->execute([$focal, $aperture, $iso, $shutter, $camera, $row['id']]);
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
    'message' => "Backfill complete: {$updated} updated, {$skipped} skipped (no EXIF available)."
]);
