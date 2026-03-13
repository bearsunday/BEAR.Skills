---
name: bear-to-alps
description: Generate ALPS profiles from existing BEAR.Sunday projects. Reads #[Alps] attributes or infers from resource structure to create ALPS profiles. Optionally adds #[Alps] attributes to resources. Use when user says "generate ALPS", "ALPSプロファイル生成", "resource to ALPS", "API documentation", or asks to create an ALPS profile from existing resources.
user-invocable: true
---

# BEAR.Sunday to ALPS Profile Generator

A skill that scans existing BEAR.Sunday projects to generate ALPS profiles.

## When to Use This Skill

- You want to create an API design document (ALPS profile) from an existing BEAR.Sunday project
- You want to add #[Alps] attributes to existing resources
- You want to visualize resource structure with ALPS
- You want to automate API design documentation

## Prerequisites

- PHP 8.1 or higher
- An existing BEAR.Sunday project
- asd (app-state-diagram) command - Used for ALPS validation and HTML generation

## Step-by-Step Process

### Step 1: Verify the Project

**Verify the following first:**

1. Confirm the project directory path
2. Identify the namespace from composer.json
3. List the resource classes under src/Resource/

See `references/extraction-tools.md` for verification commands and information source priority.

### Step 2: Mode Selection

```text
AskUserQuestion:
  Please select a generation mode:

  - Extract & Generate (Recommended)
    -> Uses #[Alps] attributes if present, otherwise infers from resource structure
    -> Generates an ALPS profile

  - Add Attributes
    -> Infers ALPS from resource structure
    -> Adds #[Alps] attributes to resource classes
    -> Also generates an ALPS profile
```

### Step 3: Scanning Resource Classes

Extract the following information from each resource class:

1. **#[Alps] attributes** - Class level (Taxonomy) and method level (Choreography)
2. **#[Link] attributes** - Transition destination information
3. **JsonSchema** - Property definitions from schema files
4. **Method parameters** - Input parameters as Ontology

See `references/extraction-tools.md` for detailed extraction examples and attribute reading patterns.

### Step 4: Building the ALPS Structure

#### 4.1 Inference from Naming Conventions (when #[Alps] is absent)

See `references/naming-conventions.md` for Taxonomy inference rules, Choreography ID patterns, and rt resolution logic.

#### 4.2 Transition Information from #[Link] and rt Resolution

See `references/naming-conventions.md` for rt resolution logic and URI-to-class mapping.

### Step 5: Generating the ALPS Profile

```json
{
  "$schema": "https://alps-io.github.io/schemas/alps.json",
  "alps": {
    "title": "{Package} API",
    "doc": {"value": "Generated from BEAR.Sunday resources"},
    "descriptor": [
      // Ontology: From method parameters and JsonSchema properties
      {"id": "userId", "title": "User ID", "def": "https://schema.org/identifier"},
      {"id": "userName", "title": "User Name", "def": "https://schema.org/name"},

      // Taxonomy: From resource classes
      {"id": "UserList", "title": "User List", "descriptor": [
        {"href": "#userId"},
        {"href": "#userName"},
        {"href": "#goUser"},
        {"href": "#doCreateUser"}
      ]},
      {"id": "User", "title": "User", "descriptor": [
        {"href": "#userId"},
        {"href": "#userName"},
        {"href": "#goUserList"},
        {"href": "#doUpdateUser"},
        {"href": "#doDeleteUser"}
      ]},

      // Choreography: From methods and #[Link]
      {"id": "goUserList", "type": "safe", "rt": "#UserList", "title": "View User List"},
      {"id": "goUser", "type": "safe", "rt": "#User", "title": "View User",
        "descriptor": [{"href": "#userId"}]},
      {"id": "doCreateUser", "type": "unsafe", "rt": "#User", "title": "Create User",
        "descriptor": [{"href": "#userName"}]},
      {"id": "doUpdateUser", "type": "idempotent", "rt": "#User", "title": "Update User",
        "descriptor": [{"href": "#userId"}, {"href": "#userName"}]},
      {"id": "doDeleteUser", "type": "idempotent", "rt": "#UserList", "title": "Delete User",
        "descriptor": [{"href": "#userId"}]}
    ]
  }
}
```

### Step 6: Output and Validation

See `references/extraction-tools.md` for validation commands and HTML generation.

### Step 7: Adding #[Alps] Attributes (Add Attributes Mode)

When the user selects "Add Attributes" mode, add the inferred ALPS IDs as attributes to resource classes.

See `references/alps-attribute-guide.md` for Before/After examples, attribute addition procedures, and #[Link] rel update rules.

## Mapping Rules

See `references/naming-conventions.md` for complete mapping tables:
- HTTP Method -> ALPS Type
- Resource Class -> Taxonomy
- JsonSchema -> Ontology

## Error Handling

### When No Resource Classes Are Found

```text
Warning: No resource classes found in src/Resource/App/

Actions:
1. Verify the project directory is correct
2. Verify composer autoload is configured
3. Verify resource classes extend ResourceObject
```

### Circular Reference Detection

```text
Warning: Circular reference detected
  UserList -> goUser -> UserDetail -> goUserList -> UserList

Actions:
This is acceptable in ALPS. Verify that the state transition diagram has a cycle.
```

### Orphaned Taxonomy

```text
Warning: No transitions defined to the following Taxonomy
  - OrphanPage

Actions:
1. Add a reference from another resource using #[Link]
2. Or remove this Taxonomy
```

## Output Summary

### Extract & Generate Mode

```markdown
## ALPS Profile Generation Complete

### Extracted Information

#### Ontology (Data Elements): {n} items
- userId, userName, email, dateCreated, ...

#### Taxonomy (States): {n} items
- UserList (from Users.php)
- User (from User.php)
- ...

#### Choreography (Transitions): {n} items
- goUserList (safe) -> UserList
- goUser (safe) -> User
- doCreateUser (unsafe) -> User
- ...

### Generated Files
- docs/alps.json
- docs/alps.html

### Next Steps
1. Review the state transition diagram:
   open docs/alps.html

2. Edit the profile as needed

3. To add #[Alps] attributes, run this skill again
   and select "Add Attributes" mode
```

### Add Attributes Mode

```markdown
## #[Alps] Attribute Addition Complete

### Updated Files ({n} files)

- src/Resource/App/Users.php
  - Class: #[Alps('UserList')]
  - onGet: #[Alps('goUserList')]
  - onPost: #[Alps('doCreateUser')]
  - #[Link] rel updated: user -> goUser, create -> doCreateUser

- src/Resource/App/User.php
  - Class: #[Alps('User')]
  - onGet: #[Alps('goUser')]
  - onPut: #[Alps('doUpdateUser')]
  - onDelete: #[Alps('doDeleteUser')]
  - #[Link] rel updated: users -> goUserList, edit -> doUpdateUser

### Additional Packages
composer require bear/api-doc (already added or needs to be added)

### Generated Files
- docs/alps.json
- docs/alps.html

### Next Steps
1. Review the updated resources
2. Run tests: composer test
3. Review the state transition diagram: open docs/alps.html
```

## References

- ALPS Specification: https://alps-io.github.io/spec/
- BEAR.Sunday Resource: https://bearsunday.github.io/manuals/1.0/en/resource.html
- BEAR.ApiDoc: https://github.com/bearsunday/BEAR.ApiDoc
- app-state-diagram: https://github.com/alps-asd/app-state-diagram
