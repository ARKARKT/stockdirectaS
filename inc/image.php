<?php
function create_image_from_file(string $file)
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file);
    finfo_close($finfo);
    switch ($mime) {
        case 'image/jpeg': return imagecreatefromjpeg($file);
        case 'image/png': return imagecreatefrompng($file);
        case 'image/webp': return imagecreatefromwebp($file);
        default: return false;
    }
}

function save_image_to_file($im, string $dest, string $mime, int $quality = 85)
{
    if ($mime === 'image/jpeg') {
        return imagejpeg($im, $dest, $quality);
    }
    if ($mime === 'image/png') {
        // convert quality 0-9 for PNG (invert)
        $pngLevel = max(0, min(9, (int)round((100 - $quality) / 11)));
        return imagepng($im, $dest, $pngLevel);
    }
    if ($mime === 'image/webp' && function_exists('imagewebp')) {
        return imagewebp($im, $dest, $quality);
    }
    // fallback to jpeg
    return imagejpeg($im, $dest, $quality);
}

function resize_and_crop(string $srcFile, string $dstFile, int $dstW, int $dstH, int $quality = 85)
{
    $info = @getimagesize($srcFile);
    if ($info === false) return false;
    $srcW = $info[0];
    $srcH = $info[1];
    $mime = $info['mime'];

    $srcImg = create_image_from_file($srcFile);
    if ($srcImg === false) return false;

    // calculate scale and crop to center
    $srcRatio = $srcW / $srcH;
    $dstRatio = $dstW / $dstH;
    if ($srcRatio > $dstRatio) {
        // source wider, crop sides
        $newH = $srcH;
        $newW = (int)round($srcH * $dstRatio);
        $srcX = (int)round(($srcW - $newW) / 2);
        $srcY = 0;
    } else {
        // source taller, crop top/bottom
        $newW = $srcW;
        $newH = (int)round($srcW / $dstRatio);
        $srcX = 0;
        $srcY = (int)round(($srcH - $newH) / 2);
    }

    $dstImg = imagecreatetruecolor($dstW, $dstH);
    // preserve transparency for PNG/WebP
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
        imagefill($dstImg, 0, 0, $transparent);
    } else {
        // white background for JPEG
        $white = imagecolorallocate($dstImg, 255,255,255);
        imagefill($dstImg, 0, 0, $white);
    }

    $resampled = imagecopyresampled($dstImg, $srcImg, 0,0, $srcX,$srcY, $dstW,$dstH, $newW,$newH);
    if (!$resampled) {
        imagedestroy($srcImg);
        imagedestroy($dstImg);
        return false;
    }

    $saved = save_image_to_file($dstImg, $dstFile, $mime, $quality);
    imagedestroy($srcImg);
    imagedestroy($dstImg);
    return $saved;
}

function generate_variants(string $fullPath, string $destDir, string $basename)
{
    // returns array of relative paths created
    $variants = [];
    ensure_dir($destDir);
    // hero (16:6) 1600x600
    $heroFile = $destDir . '/' . $basename . '-hero-1600x600.jpg';
    if (resize_and_crop($fullPath, $heroFile, 1600, 600, 85)) $variants['hero'] = $heroFile;
    // large square product 1200x1200
    $large = $destDir . '/' . $basename . '-lg-1200x1200.jpg';
    if (resize_and_crop($fullPath, $large, 1200, 1200, 85)) $variants['large'] = $large;
    // medium square 600x600
    $med = $destDir . '/' . $basename . '-md-600x600.jpg';
    if (resize_and_crop($fullPath, $med, 600, 600, 85)) $variants['medium'] = $med;
    // thumb 150x150
    $thumb = $destDir . '/' . $basename . '-thumb-150x150.jpg';
    if (resize_and_crop($fullPath, $thumb, 150, 150, 85)) $variants['thumb'] = $thumb;

    // extra mini thumb 64x64
    $mini = $destDir . '/' . $basename . '-mini-64x64.jpg';
    if (resize_and_crop($fullPath, $mini, 64, 64, 80)) $variants['mini'] = $mini;

    // extra compact (retina-ish) 300x300
    $r300 = $destDir . '/' . $basename . '-r300-300x300.jpg';
    if (resize_and_crop($fullPath, $r300, 300, 300, 85)) $variants['r300'] = $r300;

    // If WebP is available, create .webp copies for each generated JPEG
    if (function_exists('imagewebp')) {
        foreach ($variants as $k => $p) {
            $webpPath = preg_replace('/\.[^.]+$/', '.webp', $p);
            $img = create_image_from_file($p);
            if ($img !== false) {
                // save with reasonable quality
                save_image_to_file($img, $webpPath, 'image/webp', 80);
                imagedestroy($img);
                $variants[$k . '_webp'] = $webpPath;
            }
        }
    }

    // convert paths to web relative (/uploads/...)
    foreach ($variants as $k => $p) {
        $variants[$k] = '/'. str_replace('\\','/', ltrim(str_replace($_SERVER['DOCUMENT_ROOT'] ?? '', '', $p), '/'));
    }
    return $variants;
}
