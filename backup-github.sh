#!/bin/bash
# =============================================================
# backup-github.sh — Respaldo automático a GitHub
# Proyecto: Kotica Inventario
# =============================================================
# Uso manual:   bash /var/www/html/backup-github.sh
# Cron ejemplo: 0 */6 * * * bash /var/www/html/backup-github.sh >> /var/log/kotica-backup.log 2>&1
# =============================================================

set -euo pipefail

# ── Configuración ─────────────────────────────────────────────
REPO_DIR="/var/www/html"
LOG_PREFIX="[kotica-backup]"
FECHA=$(date '+%Y-%m-%d %H:%M:%S')

# ── Funciones ─────────────────────────────────────────────────
log()  { echo "$LOG_PREFIX $*"; }
fail() { echo "$LOG_PREFIX ERROR: $*" >&2; exit 1; }

# ── 1. Verificar directorio ────────────────────────────────────
cd "$REPO_DIR" || fail "No se pudo acceder a $REPO_DIR"

# ── 2. Verificar que es un repositorio Git ─────────────────────
git rev-parse --git-dir > /dev/null 2>&1 || fail "No es un repositorio Git: $REPO_DIR"

# ── 3. Verificar remoto configurado ───────────────────────────
REMOTE_URL=$(git remote get-url origin 2>/dev/null) || fail "No hay remoto 'origin' configurado. Ejecuta: git remote add origin <URL>"
log "Remoto: $REMOTE_URL"

# ── 4. Detectar rama principal ────────────────────────────────
BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null) || fail "No se pudo determinar la rama actual"
log "Rama: $BRANCH"

# ── 5. Verificar si hay cambios para commitear ────────────────
# Incluye archivos modificados, nuevos no rastreados y eliminados
if git diff --quiet && git diff --cached --quiet && [ -z "$(git ls-files --others --exclude-standard)" ]; then
    log "Sin cambios. No se realizó commit. ($FECHA)"
    exit 0
fi

# ── 6. Agregar todos los cambios ──────────────────────────────
git add .
log "Cambios agregados al staging."

# ── 7. Verificar nuevamente después del add ───────────────────
if git diff --cached --quiet; then
    log "Sin cambios en staging tras git add. No se realizó commit. ($FECHA)"
    exit 0
fi

# ── 8. Hacer commit con fecha y hora ─────────────────────────
MENSAJE="respaldo automatico: $FECHA"
git commit -m "$MENSAJE"
log "Commit realizado: $MENSAJE"

# ── 9. Push al remoto ─────────────────────────────────────────
if git push origin "$BRANCH"; then
    log "Push exitoso a origin/$BRANCH ($FECHA)"
else
    fail "Error al hacer push. Verifica credenciales y conexión a GitHub."
fi

log "Respaldo completado exitosamente. ($FECHA)"
