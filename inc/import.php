<?php
require_once __DIR__ . '/db.php';

function import_products_from_csv(string $csvPath, $vendor_id = null) {
    global $pdo;
    $result = ['created' => 0, 'errors' => []];
    if (!file_exists($csvPath)) {
        $result['errors'][] = 'CSV no encontrado';
        return $result;
    }

    if (($handle = fopen($csvPath, 'r')) === false) {
        $result['errors'][] = 'No se pudo abrir CSV';
        return $result;
    }

    $header = fgetcsv($handle);
    if (!$header) {
        $result['errors'][] = 'CSV vacío o sin cabecera';
        fclose($handle);
        return $result;
    }

    // Expected columns: sku,title,description,category,price_ars,price_usd,is_primary,is_secondary,hero,cover
    $map = array_map('strtolower', $header);
    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($map, $row);
        if (!$data) continue;
        try {
            $title = $data['title'] ?? 'Sin título';
            $title = trim($title);
            if ($title === '') {
                $result['errors'][] = 'Fila saltada: título vacío';
                continue;
            }
            $slug = substr(preg_replace('/[^a-z0-9]+/','-',strtolower($title)),0,240);
            $desc = $data['description'] ?? '';
            $category = $data['category'] ?? null;
            $price_ars = isset($data['price_ars']) ? floatval($data['price_ars']) : 0.00;
            $price_usd = isset($data['price_usd']) ? floatval($data['price_usd']) : 0.00;
            if ($price_ars < 0 || $price_usd < 0) {
                $result['errors'][] = 'Fila saltada: precio negativo para ' . $title;
                continue;
            }
            $is_primary = (!empty($data['is_primary']) && in_array(strtolower($data['is_primary']), ['1','y','yes','true'])) ? 1 : 0;
            $is_secondary = (!empty($data['is_secondary']) && in_array(strtolower($data['is_secondary']), ['1','y','yes','true'])) ? 1 : 0;

            $stmt = $pdo->prepare('INSERT INTO products (vendor_id,title,slug,description,price_ars,price_usd,is_primary,is_secondary,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute([$vendor_id, $title, $slug, $desc, $price_ars, $price_usd, $is_primary, $is_secondary]);
            $pid = $pdo->lastInsertId();

            // If hero/cover filenames provided and exist in uploads/imports, create images rows
            if (!empty($data['hero'])) {
                $heroFile = __DIR__ . '/../uploads/imports/' . trim($data['hero']);
                if (file_exists($heroFile)) {
                    $destDir = __DIR__ . '/../uploads/products/' . $pid;
                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                    $ext = pathinfo($heroFile, PATHINFO_EXTENSION) ?: 'jpg';
                    $newName = bin2hex(random_bytes(12)) . '.' . $ext;
                    $destPath = $destDir . '/' . $newName;
                    if (copy($heroFile, $destPath)) {
                        @chmod($destPath, 0644);
                        // generate variants using image helper (no upload limits)
                        require_once __DIR__ . '/image.php';
                        $basename = pathinfo($newName, PATHINFO_FILENAME);
                        $generated = generate_variants($destPath, $destDir, $basename);
                        // prefer hero variant
                        $heroRel = $generated['hero'] ?? ('/uploads/products/' . $pid . '/' . $newName);
                        $stmt = $pdo->prepare('INSERT INTO images (product_id, type, path) VALUES (?,?,?)');
                        $stmt->execute([$pid, 'hero', $heroRel]);
                        $heroId = $pdo->lastInsertId();
                        $pdo->prepare('UPDATE products SET hero_image_id = ? WHERE id = ?')->execute([$heroId, $pid]);
                    }
                }
            }
            if (!empty($data['cover'])) {
                $coverFile = __DIR__ . '/../uploads/imports/' . trim($data['cover']);
                if (file_exists($coverFile)) {
                    $destDir = __DIR__ . '/../uploads/products/' . $pid;
                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                    $ext = pathinfo($coverFile, PATHINFO_EXTENSION) ?: 'jpg';
                    $newName = bin2hex(random_bytes(12)) . '.' . $ext;
                    $destPath = $destDir . '/' . $newName;
                    if (copy($coverFile, $destPath)) {
                        @chmod($destPath, 0644);
                        require_once __DIR__ . '/image.php';
                        $basename = pathinfo($newName, PATHINFO_FILENAME);
                        $generated = generate_variants($destPath, $destDir, $basename);
                        // prefer large or medium variant for cover
                        $coverRel = $generated['large'] ?? $generated['medium'] ?? ('/uploads/products/' . $pid . '/' . $newName);
                        $stmt = $pdo->prepare('INSERT INTO images (product_id, type, path) VALUES (?,?,?)');
                        $stmt->execute([$pid, 'cover', $coverRel]);
                        $coverId = $pdo->lastInsertId();
                        $pdo->prepare('UPDATE products SET cover_image_id = ? WHERE id = ?')->execute([$coverId, $pid]);
                    }
                }
            }

            $result['created']++;
        } catch (Exception $e) {
            $result['errors'][] = 'Fila error: ' . $e->getMessage();
        }
    }
    fclose($handle);
    return $result;
}
