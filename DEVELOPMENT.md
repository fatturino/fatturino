# Development & Release Guide

## Development Workflow

### Daily Development

1. **Create feature branch from `main`:**
   ```bash
   git checkout main && git pull
   git checkout -b feature/your-feature-name
   ```

2. **Make changes, test locally:**
   ```bash
   composer dev
   vendor/bin/pint --dirty
   composer test
   ```

3. **Push branch and open PR:**
   ```bash
   git push -u origin feature/your-feature-name
   ```
   Open PR on Codeberg. Title should follow convention: `feat:`, `fix:`, `chore:`, etc.

4. **Review & Merge:**
   - At least 1 reviewer approval required (enforced via branch protection)
   - Merge to `main` via PR (no direct pushes allowed)
   - CI automatically builds `latest` and `sha-<shortsha>` image tags

### CI Pipeline on `main`

Every push to `main` triggers Woodpecker:
- **Step:** `build-main`
- **Tags pushed to registry:** 
  - `codeberg.org/fatturino/fatturino:latest` (development/staging)
  - `codeberg.org/fatturino/fatturino:sha-<8-char-hash>` (for rollback/debugging)

---

## Release Workflow

### When Ready to Release

Choose next version following [Semantic Versioning](https://semver.org/lang/it/):
- **Patch** (`0.0.X`): bugfixes only
- **Minor** (`0.X.0`): new backwards-compatible features
- **Major** (`X.0.0`): breaking changes

### Release PR (Manual Process)

1. **Create release branch:**
   ```bash
   git checkout main && git pull
   git checkout -b release/v0.0.2
   ```

2. **Update version files:**
   - Edit `VERSION` file: change to new version (e.g., `0.0.2`)
   - Edit `.env`: update `APP_VERSION=0.0.2`
   - Edit `composer.json`: update `"version": "0.0.2"`
   - Edit `CHANGELOG.md`:
     - Promote `## [Unreleased]` section to `## [0.0.2] - YYYY-MM-DD`
     - Add new empty `## [Unreleased]` at top

3. **Example CHANGELOG update:**
   ```markdown
   # Changelog
   
   ## [Unreleased]
   
   ## [0.0.2] - 2026-04-25
   
   ### Added
   - New feature description
   
   ### Fixed
   - Bug fix description
   
   ## [0.0.1] - 2026-04-22
   ...
   ```

4. **Commit and push:**
   ```bash
   git add VERSION .env CHANGELOG.md composer.json
   git commit -m "chore(release): v0.0.2"
   git push -u origin release/v0.0.2
   ```

5. **Open PR on Codeberg:**
   - Title: `chore(release): v0.0.2`
   - Review and merge to `main`

### Tag Release on Main

After release PR is merged:

```bash
git checkout main && git pull
git tag -a v0.0.2 -m "Release v0.0.2"
git push origin v0.0.2
```

### CI Pipeline on Tag

When tag `v*.*.*` is pushed, Woodpecker triggers:
- **Step:** `build-release`
- **Tags pushed to registry:**
  - `codeberg.org/fatturino/fatturino:v0.0.2` (immutable release tag)
  - `codeberg.org/fatturino/fatturino:0.0.2` (SemVer without prefix)
  - `codeberg.org/fatturino/fatturino:latest-stable` (rolling latest release)

Use `0.0.2` or `latest-stable` in production. Use `latest` only for staging/development.

---

## Image Tags Summary

| Tag | Built on | Use case | Mutable? |
|-----|----------|----------|----------|
| `latest` | main push | staging/dev | ✓ rolling |
| `sha-<hash>` | main push | rollback/debug | ✗ immutable |
| `0.0.2` | tag v0.0.2 | production release | ✗ immutable |
| `latest-stable` | tag v*.*.* | production (rolling) | ✓ latest release |

---

## Branch Protection

`main` branch is protected:
- ✓ Require PR for all changes
- ✓ Require ≥1 reviewer approval
- ✓ Require CI checks pass
- ✓ Block direct pushes
- ✓ Block force pushes

---

## Quick Reference: Release Checklist

```
[ ] Create branch: release/v0.0.2
[ ] Update VERSION file (0.0.2)
[ ] Update .env (APP_VERSION=0.0.2)
[ ] Update composer.json ("version": "0.0.2")
[ ] Update CHANGELOG.md (Unreleased → versioned)
[ ] Commit: "chore(release): v0.0.2"
[ ] Push branch and open PR
[ ] Merge PR to main
[ ] Pull main: git checkout main && git pull
[ ] Create annotated tag: git tag -a v0.0.2 -m "Release v0.0.2"
[ ] Push tag: git push origin v0.0.2
[ ] Verify Woodpecker build-release step completes
[ ] Verify image tags on registry (0.0.2, latest-stable)
```
