# ALPS Conversion Rules

## Type Mapping

| schema.org def | PHP Type | DB Type |
|----------------|----------|---------|
| schema.org/identifier | string | VARCHAR(64) |
| schema.org/name | string | VARCHAR(255) |
| schema.org/description | string | TEXT |
| schema.org/email | string | VARCHAR(255) |
| schema.org/DateTime | DateTimeInterface | DATETIME |
| schema.org/Date | DateTimeInterface | DATE |
| schema.org/Integer | int | INTEGER |
| schema.org/Number | float | DECIMAL(10,2) |
| schema.org/Boolean | bool | BOOLEAN |
| (default) | string | VARCHAR(255) |

## Name Conversion

| ALPS | PHP/JSON | Database |
|------|----------|----------|
| userId | userId | user_id |
| dateCreated | dateCreated | date_created |
| UserList | Users (class) | - |
| User | User (class) | user (table) |
