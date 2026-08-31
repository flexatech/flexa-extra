#!/usr/bin/env bash
#
# Build a distributable WordPress.org plugin zip for Flexa Extra.
#
# The archive contents are governed entirely by .distignore (the single source
# of truth). This script prefers `wp dist-archive` when available and falls
# back to an rsync+zip build that applies the same .distignore patterns, so
# both paths produce an identical file list.
#
# Usage:
#   ./release.sh              # package the current tree into dist/
#   ./release.sh --build      # rebuild the admin app (pnpm) first, then package
#   ./release.sh --help
#
# Output: dist/<slug>-<version>.zip  (internal folder: <slug>/)

set -euo pipefail

SLUG="flexa-extra"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MAIN_FILE="$ROOT/$SLUG.php"
DIST_DIR="$ROOT/dist"

BUILD_ADMIN=0

for arg in "$@"; do
    case "$arg" in
        --build) BUILD_ADMIN=1 ;;
        -h|--help)
            grep '^#' "$0" | sed 's/^# \{0,1\}//' | sed '1d'
            exit 0
            ;;
        *)
            echo "Unknown argument: $arg (try --help)" >&2
            exit 1
            ;;
    esac
done

die() { echo "release: $*" >&2; exit 1; }

[ -f "$MAIN_FILE" ] || die "main plugin file not found: $MAIN_FILE"
[ -f "$ROOT/.distignore" ] || die ".distignore not found — refusing to package without an exclude list"

# Read "Version:" from the plugin header.
VERSION="$(sed -n 's/^[[:space:]]*\*\{0,1\}[[:space:]]*Version:[[:space:]]*//p' "$MAIN_FILE" | head -n1 | tr -d '[:space:]')"
[ -n "$VERSION" ] || die "could not read Version from $MAIN_FILE header"

ZIP_PATH="$DIST_DIR/${SLUG}-${VERSION}.zip"

echo "release: $SLUG $VERSION"

# Optionally rebuild the compiled admin bundle so assets/dist is fresh.
if [ "$BUILD_ADMIN" -eq 1 ]; then
    command -v pnpm >/dev/null 2>&1 || die "--build requested but pnpm is not installed"
    echo "release: building admin app with pnpm…"
    ( cd "$ROOT/apps/admin" && pnpm install --frozen-lockfile && pnpm build )
fi

mkdir -p "$DIST_DIR"
rm -f "$ZIP_PATH"

if wp package list 2>/dev/null | grep -qi 'dist-archive'; then
    # Preferred path: wp-cli reads .distignore natively.
    echo "release: packaging via wp dist-archive…"
    wp dist-archive "$ROOT" "$ZIP_PATH" --plugin-dirname="$SLUG" >/dev/null
else
    # Fallback: stage a clean copy (rsync honours .distignore verbatim —
    # anchored /paths, comments and blank lines) then zip it under <slug>/.
    echo "release: wp dist-archive not installed — packaging via rsync + zip…"
    STAGE="$(mktemp -d)"
    trap 'rm -rf "$STAGE"' EXIT

    rsync -a --exclude-from="$ROOT/.distignore" "$ROOT/" "$STAGE/$SLUG/"
    # Never ship the build output dir or a stray zip if run from inside the tree.
    rm -rf "$STAGE/$SLUG/dist"

    ( cd "$STAGE" && zip -rqX "$ZIP_PATH" "$SLUG" )
fi

[ -f "$ZIP_PATH" ] || die "packaging failed — no zip produced"

# Self-check the archive. Capture the listing once, then grep the string —
# grep -q on a live `unzip -l | …` pipe SIGPIPEs unzip, which trips pipefail.
echo "release: verifying archive contents…"
LISTING="$(unzip -l "$ZIP_PATH")"
FAIL=0

# These must NOT be in the archive.
while IFS= read -r pattern; do
    [ -n "$pattern" ] || continue
    if grep -qF "$SLUG/$pattern" <<<"$LISTING"; then
        echo "  ✗ MUST NOT be in zip: $pattern" >&2
        FAIL=1
    fi
done <<'EXCLUDED'
includes/Register/RegisterDev.php
apps/admin/node_modules/
apps/admin/.env
vendor/
node_modules/
EXCLUDED

# The buildable source and compiled bundle MUST be present.
for pattern in apps/admin/src/ apps/admin/package.json assets/dist/admin/js/main.js "$SLUG.php"; do
    if ! grep -qF "$SLUG/$pattern" <<<"$LISTING"; then
        echo "  ✗ MISSING from zip: $pattern" >&2
        FAIL=1
    fi
done

# WordPress.org rejects hidden files (.DS_Store, stray dotfiles). Catch any
# entry whose basename starts with a dot before the reviewer's Plugin Check does.
HIDDEN="$(unzip -Z1 "$ZIP_PATH" | grep -E '(^|/)\.[^/]+$' || true)"
if [ -n "$HIDDEN" ]; then
    echo "  ✗ hidden file(s) in zip — add to .distignore:" >&2
    echo "$HIDDEN" | sed 's/^/      /' >&2
    FAIL=1
fi

[ "$FAIL" -eq 0 ] || die "archive verification failed"

SIZE="$(du -h "$ZIP_PATH" | cut -f1)"
COUNT="$(tail -n1 <<<"$LISTING" | awk '{print $2}')"
echo "release: ✓ built $ZIP_PATH ($SIZE, $COUNT files)"
