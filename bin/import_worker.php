<?php
// CLI worker to process pending product_import_jobs one by one.
// Usage: php bin/import_worker.php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/import.php';

// fetch one pending job
$job = $pdo->prepare('SELECT * FROM product_import_jobs WHERE status = "pending" ORDER BY created_at ASC LIMIT 1');
$job->execute();
$job = $job->fetch();
if (!$job) {
    echo "No pending jobs.\n";
    exit(0);
}
$jobId = $job['id'];
echo "Processing job: {$jobId} (file: {$job['filename']})\n";
$pdo->prepare('UPDATE product_import_jobs SET status = ?, log = CONCAT(IFNULL(log,''), ?) WHERE id = ?')->execute(['running', "Started: " . date('Y-m-d H:i:s') . "\n", $jobId]);

$csvPath = __DIR__ . '/../uploads/imports/' . $job['filename'];
$res = import_products_from_csv($csvPath, $job['vendor_id']);
$log = "Created: " . ($res['created'] ?? 0) . "\nErrors:\n" . implode("\n", ($res['errors'] ?? []));
$status = empty($res['errors']) ? 'completed' : 'failed';
$pdo->prepare('UPDATE product_import_jobs SET status = ?, log = CONCAT(IFNULL(log,''), ?, "\n"), processed_at = NOW() WHERE id = ?')->execute([$status, $log, $jobId]);

echo "Job {$jobId} finished: {$status}\n";

?>