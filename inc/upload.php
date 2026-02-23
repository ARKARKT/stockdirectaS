<?php
function ensure_dir(string $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

function upload_image(array $file, string $subpath = '', bool $overrideLimits = false) {
    // $file is from $_FILES
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'No se subió archivo o error en la subida.'];
    }

    $allowed = ['image/jpeg','image/png','image/webp'];
    // defaults
    $maxSize = (int)(getenv('UPLOAD_MAX_BYTES') ?: 2 * 1024 * 1024); // 2 MB
    $maxWidth = (int)(getenv('UPLOAD_MAX_WIDTH') ?: 4000);
    $maxHeight = (int)(getenv('UPLOAD_MAX_HEIGHT') ?: 4000);
    if ($overrideLimits) {
        // admin override -> effectively no practical limits
        $maxSize = PHP_INT_MAX;
        $maxWidth = 10000;
        $maxHeight = 10000;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) {
        return ['error' => 'Tipo de archivo no permitido.'];
    }

    // size check
    if (isset($file['size']) && $file['size'] > $maxSize) {
        return ['error' => 'Archivo demasiado grande. Máx ' . ($maxSize/1024/1024) . ' MB.'];
    }

    // basic image sanity check
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false) {
        return ['error' => 'Archivo no es una imagen válida.'];
    }
    [$width, $height] = [$imgInfo[0], $imgInfo[1]];
    if ($width > $maxWidth || $height > $maxHeight) {
        return ['error' => 'Dimensiones de la imagen son demasiado grandes. Máximo ' . $maxWidth . 'x' . $maxHeight . '.'];
    }

    $baseDir = __DIR__ . '/../uploads';
    if ($subpath) $targetDir = $baseDir . '/' . trim($subpath, '/');
    else $targetDir = $baseDir;
    ensure_dir($targetDir);

    // set extension based on mime to avoid spoofing
    $ext = 'jpg';
    if ($mime === 'image/png') $ext = 'png';
    if ($mime === 'image/webp') $ext = 'webp';
    if ($mime === 'image/jpeg') $ext = 'jpg';
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $targetDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['error' => 'Error guardando el archivo.'];
    }

    // After saving original, try to generate optimized variants (thumbnails)
    // require image helper
    require_once __DIR__ . '/image.php';
    $basename = pathinfo($name, PATHINFO_FILENAME);
    // destination dir for variants (use same directory)
    $variants = [];
    try {
        $fullTarget = $target;
        // generate variants under same folder
        $generated = generate_variants($fullTarget, $targetDir, $basename);
        // convert generated absolute paths to web relative when possible
        foreach ($generated as $k => $p) {
            // if p already a web path, use it, else try to build rel
            if (strpos($p, '/uploads') === 0) $variants[$k] = $p;
            else $variants[$k] = '/uploads' . ($subpath ? '/' . trim($subpath, '/') : '') . '/' . basename($p);
        }
    } catch (Exception $e) {
        // non-fatal
    }

    // Return path relative to webroot (assume /uploads/... accessible)
    $rel = '/uploads' . ($subpath ? '/' . trim($subpath, '/') : '') . '/' . $name;
    $out = ['path' => $rel, 'filename' => $name];
    if ($variants) $out['variants'] = $variants;
    return $out;
}

// Save an image provided as a data URL (base64) into uploads and generate variants
function store_image_blob(string $dataUrl, string $subpath = '') {
    if (!preg_match('#^data:(image/[^;]+);base64,(.+)$#', $dataUrl, $m)) {
        return ['error' => 'Formato de imagen inválido.'];
    }
    $mime = $m[1];
    $data = base64_decode($m[2]);
    if ($data === false) return ['error' => 'No se pudo decodificar la imagen.'];

    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) return ['error' => 'Tipo de imagen no permitido.'];
    $ext = $allowed[$mime];

    $baseDir = __DIR__ . '/../uploads';
    $targetDir = $baseDir . ($subpath ? '/' . trim($subpath, '/') : '');
    ensure_dir($targetDir);

    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $targetDir . '/' . $name;
    if (file_put_contents($target, $data) === false) return ['error' => 'Error guardando la imagen.'];

    // generate variants
    require_once __DIR__ . '/image.php';
    try {
        $basename = pathinfo($name, PATHINFO_FILENAME);
        $generated = generate_variants($target, $targetDir, $basename);
        $variants = [];
        foreach ($generated as $k => $p) {
            if (strpos($p, '/uploads') === 0) $variants[$k] = $p;
            else $variants[$k] = '/uploads' . ($subpath ? '/' . trim($subpath, '/') : '') . '/' . basename($p);
        }
    } catch (Exception $e) {
        $variants = [];
    }

    $rel = '/uploads' . ($subpath ? '/' . trim($subpath, '/') : '') . '/' . $name;
    $out = ['path' => $rel, 'filename' => $name];
    if ($variants) $out['variants'] = $variants;
    return $out;
}
