# Saved Vehicles API

Base: `/api/v1`  
Auth: Sanctum Bearer (same as `/saved-passengers`)

## Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/saved-vehicles` | List (`?query=` / `?q=` / `?type=compulsory|orange` + pagination) |
| GET | `/saved-vehicles/{id}` | Show |
| POST | `/saved-vehicles` | Create |
| PUT/PATCH | `/saved-vehicles/{id}` | Update |
| DELETE | `/saved-vehicles/{id}` | Soft delete |

## Types

- `compulsory` — requires `vehicle_type_id`, `vehicle_color_id`, `vehicle_licensing_authority_id`
- `orange` — those IDs optional; `document_type_id` / nationality / address optional

## Uniqueness

Per user on `vehicle_chassis_number` → `422` when duplicated (same pattern as passport on passengers).

## List response shape

```json
{
  "success": true,
  "message": "Saved vehicles fetched successfully.",
  "data": {
    "vehicles": [ /* items */ ]
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

`data.vehicles` mirrors `data.passengers` on the saved-passengers API.
