# JsonSchema Templates

## Response Schema

File: `var/json_schema/{entity}.json`

```json
{
  "$id": "{entity}.json",
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "{Entity}",
  "type": "object",
  "required": ["{required_fields}"],
  "properties": {
    "id": {
      "description": "The unique identifier for a {entity}.",
      "type": "string",
      "maxLength": 64
    }
  }
}
```

## Response Schema (List)

File: `var/json_schema/{entity}-list.json` (see `app/var/json_schema/todo-list.json`)

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "{Entity} List Response",
  "type": "object",
  "required": ["{entity_plural}"],
  "properties": {
    "{entity_plural}": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["{required_fields}"],
        "properties": {
          "id": {
            "description": "The unique identifier for a {entity}",
            "type": "string",
            "maxLength": 64
          }
        }
      }
    }
  }
}
```

## Request Schema (POST)

File: `var/json_validate/{entity}-post.json`

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "Create {Entity} Request",
  "type": "object",
  "required": ["{required_input_fields}"],
  "properties": {
    "{field_name}": {
      "type": "string",
      "description": "..."
    }
  }
}
```

## Request Schema (PUT)

File: `var/json_validate/{entity}-put.json`

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "Update {Entity} Request",
  "type": "object",
  "required": ["{required_update_fields}"],
  "properties": {
    "{field_name}": {
      "type": "string",
      "description": "..."
    }
  }
}
```
