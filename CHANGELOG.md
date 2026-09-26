# Changelog

All notable changes to `contacts-for-laravel` will be documented in this file.

## Unreleased

### Fixed

- vCard export is valid vCard 3.0: it always emits the required `N` property (from structured
  name attributes when the owner has them), maps labels to registered `TYPE` tokens and carries
  free-text labels as `itemN.X-ABLabel` instead of backslash-escaping them into a `TYPE`
  parameter, normalises CR/CRLF line breaks, stops escaping URLs, and folds long lines.
- `VCardExporter::forOwner()` reads contacts through the configured `contacts.model` and no
  longer throws under `Model::shouldBeStrict()` for an owner without a `name` column.
