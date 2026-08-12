# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Current state

This repository currently contains only a **Claude Design handoff bundle** — there is no implemented application yet, no build system, no lint config, and no tests. Do not invent build/test commands; none exist.

Contents:
- [README.md](README.md) — handoff instructions from Claude Design (claude.ai/design)
- [project/LinuxQuest LMS.dc.html](project/LinuxQuest%20LMS.dc.html) — the primary design prototype: a Thai-language LMS ("LinuxQuest", `ระบบเรียน Linux พร้อมเกมส์`) mocked up in HTML/CSS with a custom templating syntax
- [project/support.js](project/support.js) — a generated runtime (`dc-runtime`) that parses the `<x-dc>` template format and renders it via React; marked "do not edit, rebuild with `cd dc-runtime && bun run build`" but the `dc-runtime` source is not included in this bundle
- `project/uploads/` — reference images pasted into the design tool
- `project/.thumbnail` — thumbnail image of the design

## Working with the design file

Per [README.md](README.md), the `.dc.html` file is a **prototype, not production code**:
- It uses a custom template syntax (`<x-dc>`, `{{ expr }}`, `sc-for`, `sc-if`, `onClick="{{ handler }}"`) interpreted at runtime by `support.js`/React — this is a design-tool authoring format, not a framework to adopt.
- The goal when implementing is to **recreate the design pixel-perfectly** in whatever stack fits the target codebase (React, Vue, plain PHP/JS, etc.) — do not copy the `<x-dc>` structure or the `support.js` runtime into production code.
- Read the `.dc.html` file in full (inline `<style>` block plus the templated markup) before implementing; it defines all colors, spacing, typography, and layout inline.
- Do not render `.dc.html` in a browser or screenshot it unless explicitly asked — all needed detail (colors, dimensions, layout) is in the source markup/CSS.

## Repo context

This directory is served under XAMPP's `htdocs` (`C:\xampp\htdocs\lmln`), suggesting the eventual implementation target is a PHP-based LMS. No PHP application code exists yet — confirm the intended stack with the user before scaffolding one.
