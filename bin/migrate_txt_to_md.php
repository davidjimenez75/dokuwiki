#!/usr/bin/env php
<?php
/**
 * Script de Migración: .txt → .md
 *
 * Migra todos los archivos de páginas wiki de .txt a .md
 * Incluye: páginas actuales, attic (historial), templates
 *
 * @author Agent a103
 * @date 2025-12-30
 * @project DokuWiki Migration
 *
 * USO: php migrate_txt_to_md.php [--dry-run] [--verbose]
 *
 * IMPORTANTE: NO ejecutar hasta que todos los cambios PHP estén completos
 */

if (!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__) . '/../');

// Opciones de línea de comandos
$options = getopt('', ['dry-run', 'verbose', 'help']);
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

if (isset($options['help'])) {
    echo "Uso: php migrate_txt_to_md.php [--dry-run] [--verbose] [--help]\n";
    echo "  --dry-run  : Simula la migración sin hacer cambios\n";
    echo "  --verbose  : Muestra información detallada\n";
    echo "  --help     : Muestra esta ayuda\n";
    exit(0);
}

// Directorios a procesar
$dataDir = DOKU_INC . 'data/';
$pagesDir = $dataDir . 'pages/';
$atticDir = $dataDir . 'attic/';
$backupDir = $dataDir . 'backup_migration_' . date('Ymd_His') . '/';

// Contadores
$stats = [
    'pages_migrated' => 0,
    'attic_migrated' => 0,
    'templates_migrated' => 0,
    'errors' => 0,
    'skipped' => 0
];

echo "===========================================\n";
echo "  DokuWiki Migration: .txt → .md\n";
echo "===========================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (simulación)" : "REAL (cambios permanentes)") . "\n";
echo "===========================================\n\n";

// Crear backup antes de migrar
if (!$dryRun) {
    echo "[BACKUP] Creando backup en: $backupDir\n";
    if (!mkdir($backupDir, 0755, true)) {
        echo "[ERROR] No se pudo crear directorio de backup\n";
        exit(1);
    }

    // Copiar estructura de datos
    exec("cp -a " . escapeshellarg($pagesDir) . " " . escapeshellarg($backupDir . "pages/"));
    exec("cp -a " . escapeshellarg($atticDir) . " " . escapeshellarg($backupDir . "attic/"));
    echo "[BACKUP] Backup completado\n\n";
}

/**
 * Migra un archivo de .txt a .md
 */
function migrateFile($oldPath, $newPath, $dryRun, $verbose) {
    global $stats;

    if ($verbose) {
        echo "  [MIGRATE] $oldPath → $newPath\n";
    }

    if (!file_exists($oldPath)) {
        if ($verbose) echo "  [SKIP] Archivo no existe: $oldPath\n";
        $stats['skipped']++;
        return false;
    }

    if (file_exists($newPath)) {
        if ($verbose) echo "  [SKIP] Destino ya existe: $newPath\n";
        $stats['skipped']++;
        return false;
    }

    if (!$dryRun) {
        if (!rename($oldPath, $newPath)) {
            echo "  [ERROR] No se pudo renombrar: $oldPath\n";
            $stats['errors']++;
            return false;
        }
    }

    return true;
}

// ============================================
// FASE 1: Migrar páginas actuales (.txt → .md)
// ============================================
echo "[FASE 1] Migrando páginas actuales...\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pagesDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'txt') {
        $oldPath = $file->getPathname();
        $newPath = preg_replace('/\.txt$/', '.md', $oldPath);

        if (migrateFile($oldPath, $newPath, $dryRun, $verbose)) {
            $stats['pages_migrated']++;
        }
    }
}

echo "[FASE 1] Páginas migradas: {$stats['pages_migrated']}\n\n";

// ============================================
// FASE 2: Migrar attic (.txt.gz → .md.gz, .txt.bz2 → .md.bz2)
// ============================================
echo "[FASE 2] Migrando historial (attic)...\n";

if (is_dir($atticDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($atticDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $oldPath = $file->getPathname();
            $newPath = null;

            // .txt.gz → .md.gz
            if (preg_match('/\.txt\.gz$/', $oldPath)) {
                $newPath = preg_replace('/\.txt\.gz$/', '.md.gz', $oldPath);
            }
            // .txt.bz2 → .md.bz2
            elseif (preg_match('/\.txt\.bz2$/', $oldPath)) {
                $newPath = preg_replace('/\.txt\.bz2$/', '.md.bz2', $oldPath);
            }
            // .txt sin comprimir (raro pero posible)
            elseif (preg_match('/\.\d+\.txt$/', $oldPath)) {
                $newPath = preg_replace('/\.txt$/', '.md', $oldPath);
            }

            if ($newPath && migrateFile($oldPath, $newPath, $dryRun, $verbose)) {
                $stats['attic_migrated']++;
            }
        }
    }
}

echo "[FASE 2] Archivos attic migrados: {$stats['attic_migrated']}\n\n";

// ============================================
// FASE 3: Migrar templates (_template.txt → _template.md)
// ============================================
echo "[FASE 3] Migrando templates...\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pagesDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $basename = $file->getBasename();
        if ($basename === '_template.txt' || $basename === '__template.txt') {
            $oldPath = $file->getPathname();
            $newPath = preg_replace('/\.txt$/', '.md', $oldPath);

            if (migrateFile($oldPath, $newPath, $dryRun, $verbose)) {
                $stats['templates_migrated']++;
            }
        }
    }
}

echo "[FASE 3] Templates migrados: {$stats['templates_migrated']}\n\n";

// ============================================
// RESUMEN FINAL
// ============================================
echo "===========================================\n";
echo "  RESUMEN DE MIGRACIÓN\n";
echo "===========================================\n";
echo "Páginas migradas:   {$stats['pages_migrated']}\n";
echo "Attic migrados:     {$stats['attic_migrated']}\n";
echo "Templates migrados: {$stats['templates_migrated']}\n";
echo "Errores:            {$stats['errors']}\n";
echo "Omitidos:           {$stats['skipped']}\n";
echo "===========================================\n";

if ($dryRun) {
    echo "\n[INFO] Modo DRY-RUN: No se realizaron cambios reales.\n";
    echo "Ejecuta sin --dry-run para aplicar la migración.\n";
} else {
    echo "\n[INFO] Migración completada.\n";
    echo "Backup guardado en: $backupDir\n";
}

// Verificación post-migración
echo "\n[VERIFICACIÓN]\n";
$remainingTxt = 0;
$newMd = 0;

if (!$dryRun) {
    exec("find " . escapeshellarg($pagesDir) . " -name '*.txt' -type f | wc -l", $output);
    $remainingTxt = (int)trim($output[0] ?? '0');

    $output = [];
    exec("find " . escapeshellarg($pagesDir) . " -name '*.md' -type f | wc -l", $output);
    $newMd = (int)trim($output[0] ?? '0');

    echo "Archivos .txt restantes en pages/: $remainingTxt\n";
    echo "Archivos .md en pages/:            $newMd\n";

    if ($remainingTxt === 0 && $newMd > 0) {
        echo "\n✅ MIGRACIÓN EXITOSA\n";
        exit(0);
    } else {
        echo "\n⚠️  VERIFICAR: Aún hay archivos .txt o no hay archivos .md\n";
        exit(1);
    }
}

exit(0);
