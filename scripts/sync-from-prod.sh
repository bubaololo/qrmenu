#!/usr/bin/env bash
#
# Pull assets and (optionally) the database from the prod server into this
# project. Run locally:
#
#   scripts/sync-from-prod.sh assets        # only storage/app/{public,private}
#   scripts/sync-from-prod.sh db            # only Postgres dump + restore
#   scripts/sync-from-prod.sh all           # both (default)
#
# Flags:
#   --no-delete   Do not delete local files that no longer exist on the server
#   --keep-dump   Keep the .dump file in storage/app/dumps/
#   --dry-run     Show rsync changes without copying
#
set -euo pipefail

SSH_PORT=3022
SSH_HOST=root@217.15.166.116
REMOTE_ROOT=/srv/menu/backend
REMOTE_PG_CONTAINER=postgres

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

CMD="${1:-all}"
shift || true

DELETE_FLAG="--delete"
DRY_RUN=""
KEEP_DUMP=0

for arg in "$@"; do
    case "$arg" in
        --no-delete) DELETE_FLAG="" ;;
        --dry-run)   DRY_RUN="--dry-run" ;;
        --keep-dump) KEEP_DUMP=1 ;;
        *) echo "Unknown flag: $arg" >&2; exit 2 ;;
    esac
done

ssh_cmd() { ssh -p "$SSH_PORT" "$SSH_HOST" "$@"; }

sync_assets() {
    echo "==> Syncing storage/app/public/"
    rsync -avz $DRY_RUN $DELETE_FLAG \
        --exclude='.gitignore' \
        -e "ssh -p $SSH_PORT" \
        "$SSH_HOST:$REMOTE_ROOT/storage/app/public/" \
        "$PROJECT_ROOT/storage/app/public/"

    echo "==> Syncing storage/app/private/"
    rsync -avz $DRY_RUN $DELETE_FLAG \
        --exclude='.gitignore' \
        --exclude='livewire-tmp/' \
        -e "ssh -p $SSH_PORT" \
        "$SSH_HOST:$REMOTE_ROOT/storage/app/private/" \
        "$PROJECT_ROOT/storage/app/private/"

    if [[ ! -L "$PROJECT_ROOT/public/storage" ]]; then
        echo "==> public/storage symlink missing, running storage:link"
        php artisan storage:link
    fi
}

sync_db() {
    if [[ ! -f "$PROJECT_ROOT/.env" ]]; then
        echo "Error: $PROJECT_ROOT/.env not found" >&2
        exit 1
    fi

    # Load only DB_* vars from local .env without exposing the rest
    set -a
    # shellcheck disable=SC1090
    source <(grep -E '^DB_' "$PROJECT_ROOT/.env")
    set +a

    : "${DB_HOST:=127.0.0.1}"
    : "${DB_PORT:=5432}"
    : "${DB_DATABASE:?DB_DATABASE is required in .env}"
    : "${DB_USERNAME:?DB_USERNAME is required in .env}"
    : "${DB_PASSWORD:=}"

    local dumps_dir="$PROJECT_ROOT/storage/app/dumps"
    mkdir -p "$dumps_dir"
    local stamp; stamp="$(date +%Y%m%d-%H%M%S)"
    local dump_file="$dumps_dir/menu-$stamp.dump"
    local remote_tmp="/tmp/menu-sync-$stamp.dump"

    echo "==> Dumping prod DB to $remote_tmp (on server)"
    # Read remote .env, run pg_dump inside the postgres container, pipe out as
    # custom-format so we can pg_restore --clean --if-exists locally. Remote
    # creds never leave the server.
    ssh_cmd bash -s <<REMOTE
set -euo pipefail
cd "$REMOTE_ROOT"
set -a; source .env; set +a
docker exec -i \
    -e PGPASSWORD="\$DB_PASSWORD" \
    "$REMOTE_PG_CONTAINER" \
    pg_dump -U "\$DB_USERNAME" -d "\$DB_DATABASE" \
        --no-owner --no-acl --format=custom \
    > "$remote_tmp"
REMOTE

    echo "==> Copying dump to $dump_file"
    scp -P "$SSH_PORT" "$SSH_HOST:$remote_tmp" "$dump_file"
    ssh_cmd "rm -f $remote_tmp"

    echo "==> Terminating local connections to $DB_DATABASE"
    PGPASSWORD="$DB_PASSWORD" psql \
        -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d postgres \
        -v ON_ERROR_STOP=1 \
        -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='$DB_DATABASE' AND pid<>pg_backend_pid();" \
        > /dev/null

    echo "==> Restoring into local DB '$DB_DATABASE'"
    PGPASSWORD="$DB_PASSWORD" pg_restore \
        -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" \
        --clean --if-exists --no-owner --no-acl \
        "$dump_file"

    if [[ "$KEEP_DUMP" -eq 0 ]]; then
        rm -f "$dump_file"
        echo "==> Removed dump (pass --keep-dump to retain)"
    else
        echo "==> Dump kept at $dump_file"
    fi
}

case "$CMD" in
    assets) sync_assets ;;
    db)     sync_db ;;
    all)    sync_assets; sync_db ;;
    -h|--help|help)
        sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'
        ;;
    *)
        echo "Unknown command: $CMD (expected: assets | db | all)" >&2
        exit 2
        ;;
esac

echo "==> Done."
