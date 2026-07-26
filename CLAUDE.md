# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BEAR.Skills is a collection of Claude Skills designed for BEAR.Sunday, a PHP resource-oriented framework. The primary goal is to provide automated code generation for BEAR.Sunday's ROA (Resource Oriented Architecture) components.

The core skill is **bear-resource-gen**, which generates a complete resource set (Phinx migration, Query/Command interfaces, SQL files, Entity, ResourceObject, JsonSchema, tests) from a simple specification or an ALPS profile using the Ray.MediaQuery pattern. Its conventions (flat `var/sql/`, `var/json_schema/` + `var/json_validate/`, readonly entities, CQRS interface split) are documented in [skills/bear-resource-gen/SKILL.md](skills/bear-resource-gen/SKILL.md) — that file is the single source of truth; do not restate its details here.

## Development Commands

The `app/` directory contains a BEAR.Sunday project for testing and development:

### Setup and Dependencies
```bash
cd app
composer install
composer setup  # Runs bin/setup.php and Phinx migrations
```

### Testing
```bash
cd app
composer test          # Run PHPUnit tests
composer coverage      # Generate coverage report with Xdebug
composer pcov          # Generate coverage report with PCOV

# Run a single test file
./vendor/bin/phpunit tests/Resource/App/TodoTest.php

# Run a single test method
./vendor/bin/phpunit --filter testOnGet tests/Resource/App/TodoTest.php
```

### Code Quality
```bash
cd app
composer cs            # Check coding standards
composer cs-fix        # Fix coding standards
composer sa            # Run static analysis (Psalm, PHPStan, PHPMD)
composer tests         # Run cs + sa + test
```

### Development Server
```bash
cd app
composer serve         # Start server at http://127.0.0.1:8080
```

### Building
```bash
cd app
composer build         # Run clean + cs + sa + pcov + compile + metrics
```

## Skill Development

### Directory Structure

The same skill set is checked in three times: `skills/` (distribution, canonical), `.claude/skills/` (loaded in local Claude Code sessions), and `.agents/skills/`. **Edit `skills/` only**, then mirror the change into the other two trees before committing:

```bash
rsync -a --delete skills/ .claude/skills/
rsync -a --delete skills/ .agents/skills/
```

CI (`.github/workflows/skills-sync.yml`) fails if the trees differ. For the current skill inventory, run `ls skills/` or see the Available Skills tables in README.md — do not maintain a copy of the list here.

## Xdebug Integration

This project has Xdebug MCP server enabled for debugging PHP code:
- Use `mcp__xdebug__x-trace` for execution flow analysis
- Use `mcp__xdebug__x-profile` for performance profiling
- Use `mcp__xdebug__x-debug` for step debugging with breakpoints
- Use `mcp__xdebug__x-coverage` for test coverage analysis

Refer to the Forward Trace™ debugging approach outlined in the global CLAUDE.md.

## BEAR.Sunday Context

BEAR.Sunday is a resource-oriented PHP framework that:
- Models application states as resources (Resource Object)
- Uses dependency injection extensively (Ray.Di)
- Separates concerns through aspect-oriented programming (Ray.Aop)
- Implements hypermedia-driven architecture

Skills developed here should align with BEAR.Sunday's resource-oriented philosophy.
