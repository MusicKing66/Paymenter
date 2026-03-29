#!/usr/bin/env bash

set -Eeuo pipefail

SKIP_PULL=0
SHOW_LOGS=1
TAIL_LINES=50

usage() {
  cat <<'EOF'
Usage: ./deploy.sh [options]

Options:
  --no-pull       Skip "git pull --ff-only"
  --no-logs       Do not print recent paymenter logs
  --tail <lines>  Number of log lines to show after deploy (default: 50)
  -h, --help      Show this help message
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-pull)
      SKIP_PULL=1
      shift
      ;;
    --no-logs)
      SHOW_LOGS=0
      shift
      ;;
    --tail)
      if [[ $# -lt 2 ]]; then
        echo "--tail requires a value" >&2
        exit 1
      fi
      TAIL_LINES="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      exit 1
      ;;
  esac
done

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

require_cmd git
require_cmd docker

if ! docker compose version >/dev/null 2>&1; then
  echo "docker compose is not available" >&2
  exit 1
fi

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "$REPO_ROOT" ]]; then
  echo "This script must be run inside the git repository." >&2
  exit 1
fi

cd "$REPO_ROOT"

if [[ ! -f docker-compose.yml ]]; then
  echo "docker-compose.yml not found in $REPO_ROOT" >&2
  exit 1
fi

if [[ "$SKIP_PULL" -eq 0 ]]; then
  if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Git worktree is dirty. Commit or stash changes before deploying." >&2
    exit 1
  fi

  echo "Fetching latest changes..."
  git fetch origin

  echo "Pulling latest commit..."
  git pull --ff-only
fi

echo "Building and starting containers..."
docker compose up -d --build

echo
echo "Container status:"
docker compose ps

if [[ "$SHOW_LOGS" -eq 1 ]]; then
  echo
  echo "Recent paymenter logs:"
  docker compose logs --tail "$TAIL_LINES" paymenter
fi
