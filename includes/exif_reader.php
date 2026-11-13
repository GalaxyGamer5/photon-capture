<?php
/**
 * includes/exif_reader.php
 * Pure-PHP JPEG EXIF reader — no PHP exif extension required.
 * Parses IFD0 and ExifIFD from the APP1 segment of a JPEG.
 *
 * Returns an array with keys:
 *   camera_model, iso, focal_length, aperture, shutter_speed, taken_at
 * All values are human-readable strings or null.
 */

if (!function_exists('pure_php_read_exif')) {

function pure_php_read_exif($filePath) {
    $result = [
        'camera_model'  => null,
        'iso'           => null,
        'focal_length'  => null,
        'aperture'      => null,
        'shutter_speed' => null,
        'taken_at'      => null,
    ];

    $fp = @fopen($filePath, 'rb');
    if (!$fp) return $result;

    // Must start with JPEG SOI marker FF D8
    if (fread($fp, 2) !== "\xFF\xD8") { fclose($fp); return $result; }

    // Walk APP segments looking for APP1 (FF E1) containing "Exif\0\0"
    $tiff = null;
    while (!feof($fp)) {
        $marker = fread($fp, 2);
        if (strlen($marker) < 2) break;

        if ($marker[0] !== "\xFF") break; // Not a valid marker

        $markerByte = ord($marker[1]);

        // Skip fill bytes
        if ($markerByte === 0xFF) { fseek($fp, -1, SEEK_CUR); continue; }

        // SOD / compressed data — stop
        if ($markerByte === 0xDA) break;

        // Read segment length (includes the 2 length bytes)
        $lenData = fread($fp, 2);
        if (strlen($lenData) < 2) break;
        $segLen = unpack('n', $lenData)[1] - 2;
        if ($segLen < 0) break;

        if ($markerByte === 0xE1 && $segLen >= 14) {
            // APP1 — could be EXIF
            $segData = fread($fp, $segLen);
            if (substr($segData, 0, 6) === "Exif\0\0") {
                $tiff = substr($segData, 6);
            }
            // Even if not Exif (could be XMP), just continue
        } else {
            fseek($fp, $segLen, SEEK_CUR);
        }

        if ($tiff !== null) break;
    }
    fclose($fp);
    if ($tiff === null) return $result;

    // Parse TIFF header
    $endian = substr($tiff, 0, 2);
    $big    = ($endian === 'MM');
    $unpackShort = $big ? 'n' : 'v';
    $unpackLong  = $big ? 'N' : 'V';

    $magic = unpack($unpackShort, substr($tiff, 2, 2))[1];
    if ($magic !== 42) return $result; // Not valid TIFF

    $ifd0Offset = unpack($unpackLong, substr($tiff, 4, 4))[1];

    // Helper: rational → float
    $rational = function($data, $off) use ($unpackLong, $big) {
        if (strlen($data) < $off + 8) return null;
        $n = unpack($unpackLong, substr($data, $off, 4))[1];
        $d = unpack($unpackLong, substr($data, $off + 4, 4))[1];
        return ($d != 0) ? $n / $d : null;
    };

    // Helper: read IFD tags → array[tag] = value
    $readIFD = function($offset) use ($tiff, $unpackShort, $unpackLong, $big, $rational) {
        $tags = [];
        if ($offset + 2 > strlen($tiff)) return $tags;
        $count = unpack($unpackShort, substr($tiff, $offset, 2))[1];
        $offset += 2;

        for ($i = 0; $i < $count; $i++) {
            $entry = $offset + $i * 12;
            if ($entry + 12 > strlen($tiff)) break;

            $tag   = unpack($unpackShort, substr($tiff, $entry,     2))[1];
            $type  = unpack($unpackShort, substr($tiff, $entry + 2, 2))[1];
            $count2 = unpack($unpackLong, substr($tiff, $entry + 4, 4))[1];
            $valOff = $entry + 8;

            // Type sizes: 1=BYTE, 2=ASCII, 3=SHORT(2), 4=LONG(4), 5=RATIONAL(8)
            $sizes = [1 => 1, 2 => 1, 3 => 2, 4 => 4, 5 => 8, 7 => 1, 9 => 4, 10 => 8];
            $size  = $sizes[$type] ?? 1;
            $totalBytes = $size * $count2;

            // If data > 4 bytes, value field is an offset into TIFF data
            $dataOffset = ($totalBytes > 4)
                ? unpack($unpackLong, substr($tiff, $valOff, 4))[1]
                : $valOff;

            if ($type === 2) {
                // ASCII
                $str = substr($tiff, $dataOffset, $count2);
                $tags[$tag] = rtrim($str, "\0 ");
            } elseif ($type === 3) {
                // SHORT — could be array
                if ($count2 === 1) {
                    $tags[$tag] = unpack($unpackShort, substr($tiff, $dataOffset, 2))[1];
                } else {
                    $arr = [];
                    for ($j = 0; $j < min($count2, 8); $j++) {
                        $arr[] = unpack($unpackShort, substr($tiff, $dataOffset + $j*2, 2))[1];
                    }
                    $tags[$tag] = $arr;
                }
            } elseif ($type === 4) {
                $tags[$tag] = unpack($unpackLong, substr($tiff, $dataOffset, 4))[1];
            } elseif ($type === 5) {
                // RATIONAL
                $tags[$tag] = $rational($tiff, $dataOffset);
            }
        }
        return $tags;
    };

    // Read IFD0
    $ifd0 = $readIFD($ifd0Offset);

    // Follow ExifIFD pointer (tag 0x8769)
    $exif = [];
    if (isset($ifd0[0x8769])) {
        $exif = $readIFD($ifd0[0x8769]);
    }

    // --- Extract human-readable values ---

    // Camera model (prefer Model tag 0x0110, include Make 0x010F)
    $make  = isset($ifd0[0x010F]) ? trim($ifd0[0x010F]) : '';
    $model = isset($ifd0[0x0110]) ? trim($ifd0[0x0110]) : '';
    if ($model) {
        // Don't duplicate Make in Model (many cameras include it already)
        $result['camera_model'] = (stripos($model, $make) === false && $make)
            ? "$make $model"
            : $model;
    }

    // ISO (tag 0x8827 — ISOSpeedRatings, SHORT or SHORT array)
    if (isset($exif[0x8827])) {
        $iso = $exif[0x8827];
        $result['iso'] = 'ISO ' . (is_array($iso) ? $iso[0] : $iso);
    }

    // Focal length (tag 0x920A — RATIONAL, in mm)
    if (isset($exif[0x920A]) && $exif[0x920A] !== null) {
        $result['focal_length'] = round($exif[0x920A]) . 'mm';
    }

    // Aperture / FNumber (tag 0x829D — RATIONAL)
    if (isset($exif[0x829D]) && $exif[0x829D] !== null) {
        $result['aperture'] = 'f/' . round($exif[0x829D], 1);
    }

    // Shutter speed / ExposureTime (tag 0x829A — RATIONAL, in seconds)
    if (isset($exif[0x829A]) && $exif[0x829A] !== null) {
        $et = $exif[0x829A];
        if ($et < 1) {
            $result['shutter_speed'] = '1/' . round(1 / $et) . 's';
        } else {
            $result['shutter_speed'] = round($et, 1) . 's';
        }
    }

    // DateTimeOriginal (tag 0x9003 — ASCII, "YYYY:MM:DD HH:MM:SS")
    if (isset($exif[0x9003])) {
        $result['taken_at'] = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $exif[0x9003]);
    }

    return $result;
}

} // end if !function_exists

