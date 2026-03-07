#!/bin/bash
#
# Script de Reconstrucción Post-Migración
#
# Reconstruye índices y limpia cachés después de la migración .txt → .md
#
# @author Agent a103
# @date 2025-12-30
# @project DokuWiki Migration
#
# USO: ./rebuild_after_migration.sh [--dry-run]
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOKU_DIR="$(dirname "$SCRIPT_DIR")"
DATA_DIR="$DOKU_DIR/data"
CACHE_DIR="$DATA_DIR/cache"
INDEX_DIR="$DATA_DIR/index"
LOCK_DIR="$DATA_DIR/locks"

DRY_RUN=false
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
fi

echo "==========================================="
echo "  DokuWiki Post-Migration Rebuild"
echo "==========================================="
echo "Fecha: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Modo: $( [ "$DRY_RUN" = true ] && echo 'DRY-RUN' || echo 'REAL' )"
echo "Directorio DokuWiki: $DOKU_DIR"
echo "==========================================="
echo ""

# ============================================
# FASE 1: Limpiar caché
# ============================================
echo "[FASE 1] Limpiando caché..."

if [ -d "$CACHE_DIR" ]; then
    CACHE_COUNT=$(find "$CACHE_DIR" -type f 2>/dev/null | wc -l)
    echo "  Archivos de caché encontrados: $CACHE_COUNT"

    if [ "$DRY_RUN" = false ]; then
        # Eliminar archivos de caché (mantener estructura de directorios)
        find "$CACHE_DIR" -type f -delete 2>/dev/null || true
        echo "  ✅ Caché limpiado"
    else
        echo "  [DRY-RUN] Se eliminarían $CACHE_COUNT archivos de caché"
    fi
else
    echo "  ⚠️  Directorio de caché no encontrado: $CACHE_DIR"
fi
echo ""

# ============================================
# FASE 2: Limpiar índices de búsqueda
# ============================================
echo "[FASE 2] Limpiando índices de búsqueda..."

if [ -d "$INDEX_DIR" ]; then
    INDEX_COUNT=$(find "$INDEX_DIR" -type f 2>/dev/null | wc -l)
    echo "  Archivos de índice encontrados: $INDEX_COUNT"

    if [ "$DRY_RUN" = false ]; then
        # Eliminar archivos de índice (serán reconstruidos)
        find "$INDEX_DIR" -type f -delete 2>/dev/null || true
        echo "  ✅ Índices limpiados"
    else
        echo "  [DRY-RUN] Se eliminarían $INDEX_COUNT archivos de índice"
    fi
else
    echo "  ⚠️  Directorio de índice no encontrado: $INDEX_DIR"
fi
echo ""

# ============================================
# FASE 3: Limpiar locks antiguos
# ============================================
echo "[FASE 3] Limpiando locks antiguos..."

if [ -d "$LOCK_DIR" ]; then
    LOCK_COUNT=$(find "$LOCK_DIR" -type f 2>/dev/null | wc -l)
    echo "  Archivos de lock encontrados: $LOCK_COUNT"

    if [ "$DRY_RUN" = false ]; then
        find "$LOCK_DIR" -type f -delete 2>/dev/null || true
        echo "  ✅ Locks limpiados"
    else
        echo "  [DRY-RUN] Se eliminarían $LOCK_COUNT archivos de lock"
    fi
else
    echo "  ⚠️  Directorio de locks no encontrado: $LOCK_DIR"
fi
echo ""

# ============================================
# FASE 4: Eliminar marcadores .indexed
# ============================================
echo "[FASE 4] Limpiando marcadores de indexación..."

META_DIR="$DATA_DIR/meta"
if [ -d "$META_DIR" ]; then
    INDEXED_COUNT=$(find "$META_DIR" -name "*.indexed" -type f 2>/dev/null | wc -l)
    echo "  Marcadores .indexed encontrados: $INDEXED_COUNT"

    if [ "$DRY_RUN" = false ]; then
        find "$META_DIR" -name "*.indexed" -type f -delete 2>/dev/null || true
        echo "  ✅ Marcadores de indexación limpiados"
    else
        echo "  [DRY-RUN] Se eliminarían $INDEXED_COUNT marcadores"
    fi
else
    echo "  ⚠️  Directorio meta no encontrado: $META_DIR"
fi
echo ""

# ============================================
# FASE 5: Reconstruir índices
# ============================================
echo "[FASE 5] Reconstruyendo índices..."

INDEXER_SCRIPT="$DOKU_DIR/bin/indexer.php"

if [ -f "$INDEXER_SCRIPT" ]; then
    if [ "$DRY_RUN" = false ]; then
        echo "  Ejecutando indexer.php..."
        php "$INDEXER_SCRIPT" -c 2>&1 || {
            echo "  ⚠️  El indexer puede requerir ejecución manual"
        }
        echo "  ✅ Indexación iniciada"
    else
        echo "  [DRY-RUN] Se ejecutaría: php $INDEXER_SCRIPT -c"
    fi
else
    echo "  ⚠️  Script indexer.php no encontrado"
    echo "  Ejecuta manualmente: php bin/indexer.php -c"
fi
echo ""

# ============================================
# FASE 6: Ajustar permisos
# ============================================
echo "[FASE 6] Ajustando permisos..."

if [ "$DRY_RUN" = false ]; then
    # Asegurar que www-data puede escribir
    chown -R www-data:www-data "$DATA_DIR" 2>/dev/null || {
        echo "  ⚠️  No se pudieron cambiar permisos (requiere root)"
    }
    chmod -R 755 "$DATA_DIR" 2>/dev/null || true
    echo "  ✅ Permisos ajustados"
else
    echo "  [DRY-RUN] Se ajustarían permisos en $DATA_DIR"
fi
echo ""

# ============================================
# FASE 7: Verificación final
# ============================================
echo "[FASE 7] Verificación final..."

PAGES_DIR="$DATA_DIR/pages"
ATTIC_DIR="$DATA_DIR/attic"

# Contar archivos
TXT_PAGES=$(find "$PAGES_DIR" -name "*.txt" -type f 2>/dev/null | wc -l)
MD_PAGES=$(find "$PAGES_DIR" -name "*.md" -type f 2>/dev/null | wc -l)
TXT_ATTIC=$(find "$ATTIC_DIR" -name "*.txt*" -type f 2>/dev/null | wc -l)
MD_ATTIC=$(find "$ATTIC_DIR" -name "*.md*" -type f 2>/dev/null | wc -l)

echo ""
echo "==========================================="
echo "  RESUMEN DE VERIFICACIÓN"
echo "==========================================="
echo "Páginas:"
echo "  - Archivos .txt: $TXT_PAGES"
echo "  - Archivos .md:  $MD_PAGES"
echo ""
echo "Attic (historial):"
echo "  - Archivos .txt*: $TXT_ATTIC"
echo "  - Archivos .md*:  $MD_ATTIC"
echo "==========================================="

# Evaluar estado
if [ "$TXT_PAGES" -eq 0 ] && [ "$MD_PAGES" -gt 0 ]; then
    echo ""
    echo "✅ MIGRACIÓN VERIFICADA CORRECTAMENTE"
    echo "   No hay archivos .txt en pages/"
    echo "   Hay $MD_PAGES archivos .md"
    EXIT_CODE=0
elif [ "$TXT_PAGES" -gt 0 ] && [ "$MD_PAGES" -eq 0 ]; then
    echo ""
    echo "ℹ️  ESTADO: Pre-migración (archivos .txt originales)"
    EXIT_CODE=0
else
    echo ""
    echo "⚠️  ATENCIÓN: Estado mixto detectado"
    echo "   Hay tanto archivos .txt como .md"
    echo "   Verificar manualmente la migración"
    EXIT_CODE=1
fi

echo ""
echo "Rebuild completado: $(date '+%Y-%m-%d %H:%M:%S')"

if [ "$DRY_RUN" = true ]; then
    echo ""
    echo "[INFO] Modo DRY-RUN: No se realizaron cambios reales."
    echo "Ejecuta sin --dry-run para aplicar los cambios."
fi

exit $EXIT_CODE
