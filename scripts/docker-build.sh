#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# Build and push multi-arch Docker image to Codeberg Container Registry
#
# Usage:
#   ./scripts/docker-build.sh              # auto-generates date-based tag
#   ./scripts/docker-build.sh 1.0.0        # uses explicit tag
#   TAG=1.0.0 ./scripts/docker-build.sh    # alternative via env var
# ==============================================================================

REGISTRY="codeberg.org"
REPO="fatturino/fatturino"
IMAGE="${REGISTRY}/${REPO}"
PLATFORMS="linux/amd64,linux/arm64"
BUILDER_NAME="fatturino-multiarch"

# Determine the tag: argument > env var > auto-generated date-based tag
TAG="${1:-${TAG:-$(date -u +'%Y.%m.%d')-1}}"
SHA_SHORT="$(git rev-parse --short=7 HEAD)"

echo "==> Building ${IMAGE}:${TAG} (platforms: ${PLATFORMS})"
echo "==> SHA: ${SHA_SHORT}"

# Ensure buildx builder exists with multi-arch support
if ! docker buildx inspect "${BUILDER_NAME}" >/dev/null 2>&1; then
  echo "==> Creating buildx builder: ${BUILDER_NAME}"
  docker buildx create --name "${BUILDER_NAME}" --use --driver docker-container
else
  docker buildx use "${BUILDER_NAME}"
fi

# Login to Codeberg registry
echo "==> Logging in to ${REGISTRY}"
docker login "${REGISTRY}"

# Build and push multi-arch image with two tags: version + SHA
echo "==> Building and pushing..."
docker buildx build \
  --platform "${PLATFORMS}" \
  --tag "${IMAGE}:${TAG}" \
  --tag "${IMAGE}:${SHA_SHORT}" \
  --push \
  .

echo "==> Done!"
echo "    ${IMAGE}:${TAG}"
echo "    ${IMAGE}:${SHA_SHORT}"
