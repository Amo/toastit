.PHONY: ghcr-login build-prod

ghcr-login:
	@if [ -z "$(GHCR_USER)" ]; then \
		echo "GHCR_USER is missing."; \
		exit 1; \
	fi
	@if [ -z "$(GHCR_TOKEN)" ]; then \
		echo "GHCR_TOKEN is missing."; \
		exit 1; \
	fi
	@echo "$(GHCR_TOKEN)" | docker login ghcr.io -u "$(GHCR_USER)" --password-stdin

build-prod:
	@if [ -z "$(TAG)" ]; then \
		echo "TAG is required."; \
		echo "Example: make build-prod TAG=v0.1.0 GHCR_USER=amo"; \
		exit 1; \
	fi
	@if [ -z "$(GHCR_USER)" ]; then \
		echo "GHCR_USER is required."; \
		exit 1; \
	fi
	@if ! git rev-parse -q --verify "refs/tags/$(TAG)" >/dev/null; then \
		echo "Git tag '$(TAG)' not found locally."; \
		exit 1; \
	fi
	@APP_IMAGE_REF="ghcr.io/$(GHCR_USER_LC)/toastit:$(TAG)"; \
	INBOUND_IMAGE_REF="ghcr.io/$(GHCR_USER_LC)/toastit-inbound-smtp:$(TAG)"; \
	TMP_DIR="$$(mktemp -d)"; \
	trap 'rm -rf "$$TMP_DIR"' EXIT; \
	git archive --format=tar "$(TAG)" | tar -xf - -C "$$TMP_DIR"; \
	echo "Building and pushing $$APP_IMAGE_REF ..."; \
	docker buildx build --platform linux/amd64 -f "$$TMP_DIR/ops/Dockerfile.frankenphp" -t "$$APP_IMAGE_REF" --push "$$TMP_DIR"; \
	echo "Building and pushing $$INBOUND_IMAGE_REF ..."; \
	docker buildx build --platform linux/amd64 -f "$$TMP_DIR/docker/inbound-smtp/Dockerfile" -t "$$INBOUND_IMAGE_REF" --push "$$TMP_DIR"; \
	echo "Build completed:"; \
	echo "  $$APP_IMAGE_REF"; \
	echo "  $$INBOUND_IMAGE_REF"
