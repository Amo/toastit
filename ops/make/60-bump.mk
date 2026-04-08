.PHONY: bump

VERSION_FILE ?= VERSION
CHANGELOG_FILE ?= CHANGELOG.md
RELEASE_NOTE ?=

bump:
	@set -eu; \
	NOW="$$(date +'%B %d, %Y')"; \
	if [ -f "$(VERSION_FILE)" ]; then \
		BASE_STRING="$$(tr -d '[:space:]' < "$(VERSION_FILE)")"; \
		if [ -z "$$BASE_STRING" ]; then \
			echo "VERSION file is empty: $(VERSION_FILE)"; \
			exit 1; \
		fi; \
		LATEST_HASH="$$(git log --pretty=format:'%h' -n 1)"; \
		V_MAJOR="$$(printf '%s' "$$BASE_STRING" | cut -d. -f1)"; \
		V_MINOR="$$(printf '%s' "$$BASE_STRING" | cut -d. -f2)"; \
		V_PATCH_RAW="$$(printf '%s' "$$BASE_STRING" | cut -d. -f3)"; \
		V_PATCH="$$(printf '%s' "$$V_PATCH_RAW" | sed 's/[^0-9].*//')"; \
		if [ -z "$$V_MAJOR" ] || [ -z "$$V_MINOR" ] || [ -z "$$V_PATCH" ]; then \
			echo "VERSION must start with MAJOR.MINOR.PATCH (got: $$BASE_STRING)"; \
			exit 1; \
		fi; \
		echo "Current version: $$BASE_STRING"; \
		echo "Latest commit hash: $$LATEST_HASH"; \
		SUGGESTED_VERSION="$$V_MAJOR.$$V_MINOR.$$((V_PATCH + 1))"; \
		printf "Enter a version number [%s]: " "$$SUGGESTED_VERSION"; \
		read -r INPUT_STRING; \
		if [ -z "$$INPUT_STRING" ]; then \
			INPUT_STRING="$$SUGGESTED_VERSION"; \
		fi; \
		if git describe --exact-match --tags HEAD >/dev/null 2>&1; then \
			echo "Current code is already released (HEAD is tagged)."; \
			exit 0; \
		fi; \
		echo "Will set new version to $$INPUT_STRING"; \
		printf '%s\n' "$$INPUT_STRING" > "$(VERSION_FILE)"; \
		TMP_CHANGELOG="$$(mktemp)"; \
		printf '## %s (%s)\n' "$$INPUT_STRING" "$$NOW" > "$$TMP_CHANGELOG"; \
		if git rev-parse -q --verify "refs/tags/v$$BASE_STRING" >/dev/null; then \
			git log --no-merges --pretty=format:'  - %s' "v$$BASE_STRING"...HEAD >> "$$TMP_CHANGELOG"; \
		else \
			git log --no-merges --pretty=format:'  - %s' >> "$$TMP_CHANGELOG"; \
		fi; \
		printf '\n\n' >> "$$TMP_CHANGELOG"; \
		if [ -f "$(CHANGELOG_FILE)" ]; then \
			cat "$(CHANGELOG_FILE)" >> "$$TMP_CHANGELOG"; \
		fi; \
		mv "$$TMP_CHANGELOG" "$(CHANGELOG_FILE)"; \
		echo "Now you can make adjustments to $(CHANGELOG_FILE). Then press enter to continue."; \
		read -r _; \
		echo "Pushing new version to origin..."; \
		git add "$(CHANGELOG_FILE)" "$(VERSION_FILE)"; \
		git commit -m "Bump version to $$INPUT_STRING."; \
		if [ -n "$(RELEASE_NOTE)" ]; then \
			git tag -a "v$$INPUT_STRING" -m "$(RELEASE_NOTE)"; \
		else \
			git tag -a "v$$INPUT_STRING" -m "Tag version $$INPUT_STRING."; \
		fi; \
		git push origin; \
		git push origin --tags; \
	else \
		echo "Could not find $(VERSION_FILE)."; \
		printf "Do you want to create a version file and start from scratch? [y]: "; \
		read -r RESPONSE; \
		case "$$RESPONSE" in \
			""|y|Y|yes|Yes|YES) \
				printf '0.1.0\n' > "$(VERSION_FILE)"; \
				TMP_CHANGELOG="$$(mktemp)"; \
				printf '## 0.1.0 (%s)\n' "$$NOW" > "$$TMP_CHANGELOG"; \
				git log --no-merges --pretty=format:'  - %s' >> "$$TMP_CHANGELOG"; \
				printf '\n\n' >> "$$TMP_CHANGELOG"; \
				if [ -f "$(CHANGELOG_FILE)" ]; then \
					cat "$(CHANGELOG_FILE)" >> "$$TMP_CHANGELOG"; \
				fi; \
				mv "$$TMP_CHANGELOG" "$(CHANGELOG_FILE)"; \
				echo "Now you can make adjustments to $(CHANGELOG_FILE). Then press enter to continue."; \
				read -r _; \
				echo "Pushing new version to origin..."; \
				git add "$(VERSION_FILE)" "$(CHANGELOG_FILE)"; \
				git commit -m "Add VERSION and CHANGELOG.md files, Bump version to v0.1.0."; \
				if [ -n "$(RELEASE_NOTE)" ]; then \
					git tag -a "v0.1.0" -m "$(RELEASE_NOTE)"; \
				else \
					git tag -a "v0.1.0" -m "Tag version 0.1.0."; \
				fi; \
				git push origin; \
				git push origin --tags; \
				;; \
			*) \
				echo "Aborted."; \
				exit 0; \
				;; \
		esac; \
	fi; \
	echo "Finished."
