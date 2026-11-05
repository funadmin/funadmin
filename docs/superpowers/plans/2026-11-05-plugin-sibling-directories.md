# Plugin Sibling Directories Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Separate framework plugin management and installed backend plugins into sibling plugin/plugins directories.

**Architecture:** Preserve native Webman loading and current lifecycle. Rename canonical backend package/publication paths, namespaces and resource identities together.

**Tech Stack:** PHP 8.5, Webman, PHPUnit, Vue/Vite.

**Spec:** `docs/superpowers/specs/2026-11-05-plugin-sibling-directories-design.md`

## Global Constraints

- Management: `app/admin/plugin`; installed modules: `app/admin/plugins/{code}`.
- Package source: `plugins/{code}/app/admin/plugins/{code}`.
- URLs, permissions, frontend directories and lifecycle policies stay unchanged.
- Preserve parallel CMS/Hook/frontend changes; no production migrations or process reload.

## Review Focus

- Plugin users retain exact permissions and activation checks after namespace changes.
- Core permission normalization preserves database identifiers for moved controllers.
- Publication and recovery resolve source and target paths and refuse old business materials.
- CMS source autoload/testing and bundled market/example references move consistently.
- Routes remain distinct and source package/installed directories match.

### Task 1: Lock the canonical structure

**Files:** layout/scaffold tests and new plan/spec.

- [x] Update tests to expect sibling directories; add old business rejection regression.
- [x] Run them and observe failures before implementation.

### Task 2: Update implementation and move modules

**Files:** SDK scaffold/manifest/execution gate, plugin services/publishers, authorization/middleware, CRUD and generation boundary, built-in modules and CMS source/tests.

- [x] Rename exact plugin business path/namespace references and physical directories, preserving file contents.
- [x] Update canonical validation and registry parsing/ownership keys; preserve permission object normalization.
- [x] Run generation, publication, permission, lifecycle and CMS tests; fix integration gaps.

### Additional confirmed scope: Remove the empty BackendModel wrapper

- [x] Delete `app/admin/model/BackendModel.php` and use `app/common/model/BaseModel.php` directly in management models and generated models.
- [x] Verify generated model persistence, JSON casts, soft deletion/restoration and an entity named `BaseModel`.

### Task 3: Verify and document

**Files:** current plugin docs and verification record.

- [x] Replace current directory trees and mark historical specs superseded.
- [x] Run scoped tests, PHP lint and full backend suite; classify baseline or parallel-work failures. See `2026-11-05-plugin-sibling-directories-verification.md`.
- [x] Request independent read-only review and fix material findings. No Important or Critical findings remained.

Execution: native in the existing authorized checkout; no extra approval question for this confirmed layout.
