# AGENTS.md

## Project overview
This repository is a Laravel API application for inventory and sales management.

The expected architecture is:
- `routes/api.php` for API routes
- `app/Http/Controllers` for thin controllers
- `app/Services` for business logic
- `app/Models` for database entities
- `app/Helpers/ApiResponse.php` for consistent API responses
- `database/migrations` for schema changes
- `tests` for behavior verification

## Core rules

### 1. Respect the layered architecture
- Do not add business logic directly in routes or controllers.
- Keep controllers focused on request/response handling.
- Put real logic in the matching service class.
- Use Eloquent models for database interaction.

### 2. User ownership and authentication
- This app is user-scoped. Most records should be tied to the authenticated user through `user_id`.
- Always use the explicit API guard when reading the current user.
- Prefer `Auth::guard('api')->id()` or the equivalent safe pattern.
- Do not allow data creation with a missing user when the model requires ownership.
- If no authenticated user exists, throw an authentication error.

### 3. Stock integrity rules
- Any change to inventory quantity must happen inside a DB transaction.
- Validate stock before decrementing.
- When stock changes, record the movement in `StoreQuantityHistory`.
- Do not modify stock values without writing the corresponding history record.
- Prevent duplicate products in the same invoice/order where it is invalid.

### 4. API conventions
- Always use `ApiResponse` for success and error responses.
- Keep response structure consistent with the existing project style.
- Do not return inconsistent JSON structures.
- Keep error messages clear and human-readable.

### 5. Validation and data safety
- Validate request input before saving.
- Prefer Laravel validation or request classes when appropriate.
- Reject invalid IDs or duplicate items early.
- Use explicit checks for missing products and invalid product IDs.

### 6. Saving new features
When adding a new feature, follow this order:
1. Check relevant model and service.
2. Update the route in `routes/api.php`.
3. Add or update controller method.
4. Implement business logic in the corresponding service.
5. Validate input.
6. Add real tests.
7. Run `php artisan test`.

### 7. Naming conventions
- Controllers: `*Controller.php`
- Services: `*Service.php`
- Models: singular names like `Store`, `Order`, `Customer`, `User`
- Keep method naming consistent with the codebase style.

### 8. Test requirements
- Add automated tests for new behavior.
- Prefer feature tests for request flows and service behavior.
- Avoid mock-only assertions that do not validate real behavior.
- Run the full test suite after changes.

### 9. Do not violate the project pattern
- Do not place SQL or business logic inside routes.
- Do not bypass `ApiResponse`.
- Do not bypass auth checks on protected endpoints.
- Do not add duplicated logic if an existing service already handles the flow.
- Do not create records without considering ownership and audit history.

## Required verification before completion
Before considering a task complete, run:

```bash
php artisan test
```

If a task changes business logic related to stock, orders, invoices, or user ownership, also add focused tests for the changed behavior.

## Preferred implementation style
Keep the project clean, predictable, and production-safe:
- small controllers
- robust services
- authenticated, user-scoped data
- standard API response structure
- tested business logic
