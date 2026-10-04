# Project Rules for StoreF

## 1. Project type
- This project is a Laravel API backend for inventory and sales management.
- Main API routes live in `routes/api.php`.
- Business logic should live in `app/Services`.
- Controllers should stay thin and only orchestrate requests and responses.

## 2. Architecture pattern
Follow this pattern strictly:

- Controller: receive HTTP request and validate high-level flow
- Service: implement business logic
- Model: represent database entities
- Request classes: validate input when needed
- Helpers: return standard API responses

Do not add business logic directly inside controllers.

## 3. Authentication and user ownership
- This app uses API JWT authentication.
- All user-related records must be tied to the authenticated user.
- Always prefer explicit API guard access, for example: `Auth::guard('api')->id()` instead of relying on default auth behavior.
- Never create records with `user_id = null` when a user is required.
- If there is no authenticated user, throw an authentication error rather than allowing invalid data.

## 4. API response convention
- Use the standardized response helper from `app/Helpers/ApiResponse.php`.
- Response format should be consistent:
  - success: `status`, `message`, `data`, optional `pagination`
  - error: `status`, `message`, `data`
- Do not return raw arrays or responses with inconsistent shapes.

## 5. Stock and inventory safety
- Any operation that changes stock quantity must be wrapped in a database transaction.
- Always validate stock before decrementing.
- When adding or removing inventory, also write to `StoreQuantityHistory`.
- Never update product stock without updating history in the same business flow.

## 6. Naming and structure
- Use existing naming conventions:
  - Controllers: `*Controller.php`
  - Services: `*Service.php`
  - Models: `*Model.php` or singular names like `Store`, `Order`, `Customer`
- Keep methods descriptive and consistent with existing code style.
- Reuse `BaseService` whenever possible instead of creating duplicated logic.

## 7. Validation and data integrity
- Validate user input before modifying database records.
- Check for duplicate products/items where required.
- Prefer explicit checks for product existence before referencing it.
- Use Laravel validation or Request classes instead of ad hoc logic when possible.

## 8. Database and relations
- Keep user-scoped filtering consistent by `user_id` when a model supports it.
- Use Eloquent relations instead of manual join logic when possible.
- When adding a new model, also consider relation and ownership rules.

## 9. Tests
- Add tests for new behavior using Pest.
- Keep tests realistic: cover actual business behavior, not mock-only behavior.
- Prefer feature tests for API flows and service behavior.
- Run `php artisan test` after changes.

## 10. Do not change project conventions casually
- Do not add new patterns that conflict with the current Laravel API architecture.
- Do not put business logic in routes.
- Do not bypass `ApiResponse`.
- Do not bypass auth checks for protected endpoints.
- Do not add new database writes without considering stock history and user ownership.

## 11. When adding a new feature
1. Check the matching service and model.
2. Add or update the route in `routes/api.php`.
3. Add or update the controller method.
4. Implement business logic in the service.
5. Add validation and tests.
6. Run the test suite.

## 12. Required verification before completion
Every feature or fix must be checked with the project’s test suite before being considered complete.

Command:
```bash
php artisan test
```

## 13. Final coding preference
Keep the project clean, consistent, and safe. The preferred design is:
- simple controllers
- strong services
- scoped data by user
- consistent API responses
- tested business behavior
