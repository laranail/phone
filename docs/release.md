# Release

Versioning, tagging, and how the changelog gets into the GitHub release.

## Versioning

Semantic versioning. While the package is pre-1.0, the minor is the breaking-change segment: `0.2.0`
may break what `0.1.x` did.

Consumers constrain on `^0.1`.

## What counts as breaking

- Removing or renaming a public method, or narrowing a parameter type.
- Changing what a stored value means — a different default `storeFormat`, a changed cast output.
- Raising the PHP or Laravel floor.
- Changing a config key, or the meaning of an existing one.

A libphonenumber upgrade is **not** breaking on its own, even though it can change an answer: a
number that was invalid last release may be valid this one. That is the library tracking reality, and
pinning against it would be worse.

## Cutting a release

1. Update `CHANGELOG.md` under a new `## [X.Y.Z]` heading, following Keep a Changelog.
2. Commit.
3. Tag `vX.Y.Z` and push the tag.

```bash
git tag v0.2.0
git push origin v0.2.0
```

CI extracts that version's changelog section and passes it as the GitHub release body. A release
without a real description is incomplete — auto-generated notes or a "see CHANGELOG" stub do not
count.

## The `branch-alias`

`composer.json` carries a `branch-alias` mapping `dev-main` to the current line, so a path or dev
checkout still satisfies a `^0.1` constraint:

```json
"extra": {
    "branch-alias": { "dev-main": "0.1.x-dev" }
}
```

Bump it when the line moves. The alias key must match the actual branch name — `dev-main` on a repo
sitting on `master` resolves as `dev-master`, which has no alias and satisfies nothing.

## Never leave an unreachable tag

A tag that is not an ancestor of `main` is worse than no tag. Composer's VCS driver reads tags, not
reachability, so it will happily offer an orphaned `v0.4.0` as the newest version while `main` is
0.1-line code — and anyone writing `^0.4` silently gets abandoned history.

If history is rewritten, delete and re-cut every affected tag.

## Distribution

`laranail/*` packages resolve through git VCS repositories rather than Packagist. Consumers add:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/phone" }
]
```

Composer ignores a dependency's own `repositories`, so a root package must declare the whole
transitive `laranail/*` closure, not just its direct dependencies.

## No lock file

`composer.lock` is not tracked. In a library it records a resolution consumers never use, and it goes
stale invisibly because CI resolves fresh.

---

[← Docs index](../README.md#documentation)
