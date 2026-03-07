#!/usr/bin/env php
<?php
/**
 * Script de Rollback: .md → .txt
 *
 * Revierte la migración de .md a .txt
 * Puede usar backup o renombrar archivos existentes
 *
 * @author Agent a103
 * @date 2025-12-30
 * @project DokuWiki Migration
 *
 * USO: php rollback_md_to_txt.php [--from-backup=/path] [--dry-run] [--verbose]
 */

if (!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__) . '/../');

// Opciones de línea de comandos
$options = getopt('', ['from-backup:', 'dry-run', 'verbose', 'help']);
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$backupPath = $options['from-backup'] ?? null;

if (isset($options['help'])) {
    echo "Uso: php rollback_md_to_txt.php [opciones]\n";
    echo "\nOpciones:\n";
    echo "  --from-backup=/path : Restaura desde un backup específico\n";
    echo "  --dry-run           : Simula el rollback sin hacer cambios\n";
    echo "  --verbose           : Muestra información detallada\n";
    echo "  --help              : Muestra esta ayuda\n";
    echo "\nEjemplos:\n";
    echo "  php rollback_md_to_txt.php --dry-run\n";
    echo "  php rollback_md_to_txt.php --from-backup=/var/www/html/dokuwiki/data/backup_migration_20251230_150000/\n";
    exit(0);
}

// Directorios
$dataDir = DOKU_INC . 'data/';
$pagesDir = $dataDir . 'pages/';
$atticDir = $dataDir . 'attic/';

// Contadores
$stats = [
    'pages_reverted' => 0,
    'attic_reverted' => 0,
    'templates_reverted' => 0,
    'errors' => 0,
    'skipped' => 0
];

echo "===========================================\n";
echo "  DokuWiki Rollback: .md → .txt\n";
echo "===========================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (simulación)" : "REAL (cambios permanentes)") . "\n";
if ($backupPath) {
    echo "Restaurando desde: $backupPath\n";
}
echo "===========================================\n\n";

/**
 * Revierte un archivo de .md a .txt
 */
function revertFile($mdPath, $txtPath, $dryRun, $verbose) {
    global $stats;

    if ($verbose) {
        echo "  [REVERT] $mdPath → $txtPath\n";
    }

    if (!file_exists($mdPath)) {
        if ($verbose) echo "  [SKIP] Archivo no existe: $mdPath\n";
        $stats['skipped']++;
        return false;
    }

    if (file_exists($txtPath)) {
        if ($verbose) echo "  [SKIP] Destino ya existe: $txtPath\n";
        $stats['skipped']++;
        return false;
    }

    if (!$dryRun) {
        if (!rename($mdPath, $txtPath)) {
            echo "  [ERROR] No se pudo renombrar: $mdPath\n";
            $stats['errors']++;
            return false;
        }
    }

    return true;
}

// ============================================
// OPCIÓN A: Restaurar desde backup
// ============================================
if ($backupPath) {
    echo "[MODO] Restaurando desde backup...\n\n";

    if (!is_dir($backupPath)) {
        echo "[ERROR] Directorio de backup no existe: $backupPath\n";
        exit(1);
    }

    $backupPages = $backupPath . '/pages/';
    $backupAttic = $backupPath . '/attic/';

    if (!is_dir($backupPages)) {
        echo "[ERROR] No se encontró pages/ en el backup\n";
        exit(1);
    }

    if (!$dryRun) {
        echo "[RESTORE] Eliminando datos actuales...\n";
        exec("rm -rf " . escapeshellarg($pagesDir) . "*");
        exec("rm -rf " . escapeshellarg($atticDir) . "*");

        echo "[RESTORE] Copiando desde backup...\n";
        exec("cp -a " . escapeshellarg($backupPages) . ". " . escapeshellarg($pagesDir));
        if (is_dir($backupAttic)) {
            exec("cp -a " . escapeshellarg($backupAttic) . ". " . escapeshellarg($atticDir));
        }

        echo "[RESTORE] Restauración completada desde backup\n";
    } else {
        echo "[DRY-RUN] Se restauraría desde: $backupPath\n";
    }

    exit(0);
}

// ============================================
// OPCIÓN B: Renombrar .md → .txt (sin backup)
// ============================================

// FASE 1: Revertir páginas actuales (.md → .txt)
echo "[FASE 1] Revirtiendo páginas actuales...\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pagesDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'md') {
        $mdPath = $file->getPathname();
        $txtPath = preg_replace('/\.md$/', '.txt', $mdPath);

        if (revertFile($mdPath, $txtPath, $dryRun, $verbose)) {
            $stats['pages_reverted']++;
        }
    }
}

echo "[FASE 1] Páginas revertidas: {$stats['pages_reverted']}\n\n";

// FASE 2: Revertir attic (.md.gz → .txt.gz, .md.bz2 → .txt.bz2)
echo "[FASE 2] Revirtiendo historial (attic)...\n";

if (is_dir($atticDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($atticDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $mdPath = $file->getPathname();
            $txtPath = null;

            // .md.gz → .txt.gz
            if (preg_match('/\.md\.gz$/', $mdPath)) {
                $txtPath = preg_replace('/\.md\.gz$/', '.txt.gz', $mdPath);
            }
            // .md.bz2 → .txt.bz2
            elseif (preg_match('/\.md\.bz2$/', $mdPath)) {
                $txtPath = preg_replace('/\.md\.bz2$/', '.txt.bz2', $mdPath);
            }
            // .md sin comprimir
            elseif (preg_match('/\.\d+\.md$/', $mdPath)) {
                $txtPath = preg_replace('/\.md$/', '.txt', $mdPath);
            }

            if ($txtPath && revertFile($mdPath, $txtPath, $dryRun, $verbose)) {
                $stats['attic_reverted']++;
            }
        }
    }
}

echo "[FASE 2] Archivos attic revertidos: {$stats['attic_reverted']}\n\n";

// FASE 3: Revertir templates (_template.md → _template.txt)
echo "[FASE 3] Revirtiendo templates...\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pagesDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $basename = $file->getBasename();
        if ($basename === '_template.md' || $basename === '__template.md') {
            $mdPath = $file->getPathname();
            $txtPath = preg_replace('/\.md$/', '.txt', $mdPath);

            if (revertFile($mdPath, $txtPath, $dryRun, $verbose)) {
                $stats['templates_reverted']++;
            }
        }
    }
}

echo "[FASE 3] Templates revertidos: {$stats['templates_reverted']}\n\n";

// ============================================
// RESUMEN FINAL
// ============================================
echo "===========================================\n";
echo "  RESUMEN DE ROLLBACK\n";
echo "===========================================\n";
echo "Páginas revertidas:   {$stats['pages_reverted']}\n";
echo "Attic revertidos:     {$stats['attic_reverted']}\n";
echo "Templates revertidos: {$stats['templates_reverted']}\n";
echo "Errores:              {$stats['errors']}\n";
echo "Omitidos:             {$stats['skipped']}\n";
echo "===========================================\n";

if ($dryRun) {
    echo "\n[INFO] Modo DRY-RUN: No se realizaron cambios reales.\n";
} else {
    echo "\n[INFO] Rollback completado.\n";
    echo "\n⚠️  IMPORTANTE: También debes revertir los cambios en los archivos PHP:\n";
    echo "   - inc/pageutils.php\n";
    echo "   - inc/search.php\n";
    echo "   - inc/TreeBuilder/PageTreeBuilder.php\n";
    echo "   - inc/common.php\n";
    echo "   - inc/Action/Export.php\n";
}

// Verificación post-rollback
echo "\n[VERIFICACIÓN]\n";
if (!$dryRun) {
    $output = [];
    exec("find " . escapeshellarg($pagesDir) . " -name '*.txt' -type f | wc -l", $output);
    $txtCount = (int)trim($output[0] ?? '0');

    $output = [];
    exec("find " . escapeshellarg($pagesDir) . " -name '*.md' -type f | wc -l", $output);
    $mdCount = (int)trim($output[0] ?? '0');

    echo "Archivos .txt en pages/: $txtCount\n";
    echo "Archivos .md en pages/:  $mdCount\n";

    if ($mdCount === 0 && $txtCount > 0) {
        echo "\n✅ ROLLBACK EXITOSO\n";
        exit(0);
    } else {
        echo "\n⚠️  VERIFICAR: Aún hay archivos .md o no hay archivos .txt\n";
        exit(1);
    }
}

exit(0);
