# JsonSchema Templates

## Response Schema

File: `var/schema/response/{entity}.json`

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

## Request Schema (POST)

File: `var/schema/request/{entity}-post.json`

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

File: `var/schema/request/{entity}-put.json`

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
