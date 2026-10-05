# Public release history replacement runbook

This runbook creates a clean public-history release without modifying the current checkout automatically. It is destructive after the remote force-push. Execute it only with explicit maintainer approval and a maintenance window.

## Preconditions

1. Freeze merges and deployments.
2. Confirm the release branch contains only reviewed source, documentation, lockfiles, and intentional public assets.
3. Run the full verification sequence in [DEVELOPMENT.md](../DEVELOPMENT.md).
4. Scan the candidate tree and all refs for secrets and personal data. Rotate any credential ever exposed before publication, even if history will be removed.
5. Download and verify a full bare mirror, release artifacts, database backup, document-storage backup, and current image digest.

## Create and verify a local clean root commit

Run these commands in a disposable clone, not in the production working tree:

```sh
git clone --mirror <private-remote> fatturino-private-backup.git
git clone <private-remote> fatturino-public-release
cd fatturino-public-release
git checkout <reviewed-release-commit>
git switch --orphan public-release
git add -A
git commit -m "chore(release): public vX.Y.Z"
git fsck --full
git log --oneline --all
git grep -nEI 'AKIA[0-9A-Z]{16}|-----BEGIN .*PRIVATE KEY-----|gh[pousr]_[A-Za-z0-9_]{20,}|github_pat_[A-Za-z0-9_]{20,}|sk_(live|test)_' public-release
```

The final `git grep` must return no matches. Independently inspect all files before proceeding. Keep the mirror inaccessible to the public remote.

## Publish

Create a new empty public repository or ensure every maintainer approves overwriting the existing remote. Push the orphan branch first, then set it as default and protect it:

```sh
git remote add public <public-remote>
git push public public-release:main
git tag -a vX.Y.Z -m "Public release vX.Y.Z"
git push public vX.Y.Z
```

Enable protected `main`, required review, and required CI checks in the forge. Configure the package registry permissions and repository secrets after the first push; do not transfer private-repository secrets blindly.

## Rollback

If validation fails before switching the default branch, delete the public repository or branch and recreate it from the verified private mirror. If it fails after publication, immediately make the repository private, revoke and rotate credentials, disable affected integrations, notify maintainers, and republish a corrected clean root commit. The private bare mirror is the recovery source; never rely on force-pushed public history as a backup.
