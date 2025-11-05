#!/usr/bin/env bash

set -euo pipefail

# Build configuration
IMAGE_NAME="iwebercodes/webhook-receiver"
IMAGE_TAG="${1:-latest}"
FULL_IMAGE_NAME="${IMAGE_NAME}:${IMAGE_TAG}"

echo "=========================================="
echo "Building Webhook Receiver Docker Image"
echo "=========================================="
echo "Image: ${FULL_IMAGE_NAME}"
echo ""

# Build the image
echo "Building Docker image..."
docker build -t "${FULL_IMAGE_NAME}" .

echo ""
echo "=========================================="
echo "Build Complete!"
echo "=========================================="
echo ""
echo "Image: ${FULL_IMAGE_NAME}"
echo ""
echo "Usage:"
echo "  # Run the container"
echo "  docker run -d -p 8080:80 -v webhook-data:/var/www/html/var/data ${FULL_IMAGE_NAME}"
echo ""
echo "  # Or use in docker-compose.yml:"
echo "  services:"
echo "    webhook-receiver:"
echo "      image: ${FULL_IMAGE_NAME}"
echo "      ports:"
echo "        - \"8080:80\""
echo "      volumes:"
echo "        - webhook-data:/var/www/html/var/data"
echo ""
