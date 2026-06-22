# Fix Wayfinder .form() missing

## Problem
`ProfileController.update.form is not a function` — after regenerating Wayfinder without `--with-form` flag, the `.form()` methods were dropped from generated files.

## Fix
Run: `php artisan wayfinder:generate --with-form`

This regenerates all files in `resources/js/actions/` and `resources/js/routes/` with `.form()` methods included.

## Files
- `resources/js/actions/**` — auto-regenerated
- `resources/js/routes/**` — auto-regenerated

## Verify
- Open `/settings/profile` — form should render without TypeError
- Check `resources/js/actions/App/Http/Controllers/Settings/ProfileController.ts` has `.form` property on `update`
