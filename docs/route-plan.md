# Route and controller migration plan

This document inventories the WordPress-facing AJAX actions and shortcodes that ship with ARM and proposes equivalent FastRoute endpoints plus controller responsibilities for the standalone application. It also outlines how public forms, dashboards, and payment/webhook surfaces are ported to controller methods that render Twig/Blade views or emit JSON while preserving CSRF protection.

## AJAX action inventory and HTTP route mapping

| WordPress hook | Module / file | Responsibility (existing) | Proposed HTTP route & method | Controller target | Auth/CSRF notes |
| --- | --- | --- | --- | --- | --- |
| `wp_ajax_arm_get_slots`, `wp_ajax_nopriv_arm_get_slots` | `includes/appointments/Ajax.php` | Return available appointment slots for date/time filters. | `POST /api/appointments/slots` | `AppointmentsController::slots()` | Public with CSRF token; customer submissions only. |
| `wp_ajax_arm_admin_events`, `wp_ajax_arm_save_event` | `includes/appointments/Admin.php` | Admin calendar feed + event saves. | `GET /api/appointments/admin/events`, `POST /api/appointments/admin/events` | `AppointmentsController::adminEvents()`, `AppointmentsController::saveEvent()` | Requires authenticated staff capability. |
| `wp_ajax_arm_re_search_customers` | `includes/estimates/Controller.php`, `includes/admin/{Customers,Class-Customers}.php` | Customer search lookups for admin. | `GET /api/customers/search` | `CustomersController::search()` | Staff-auth only. |
| `wp_ajax_arm_re_customer_vehicles` | `includes/estimates/Controller.php` | Fetch vehicles for a customer. | `GET /api/customers/{id}/vehicles` | `CustomersController::vehicles($id)` | Staff-auth only. |
| `wp_ajax_nopriv_arm_re_est_accept`, `wp_ajax_nopriv_arm_re_est_decline` | `includes/estimates/Ajax.php` | Accept/decline estimate from public link. | `POST /api/estimates/{id}/accept`, `POST /api/estimates/{id}/decline` | `EstimatesController::accept()`, `EstimatesController::decline()` | Public but signed/nonce verified. |
| `wp_ajax_arm_re_get_bundle_items` | `includes/bundles/{Controller,Ajax}.php` | Retrieve bundle item list. | `GET /api/bundles/{id}/items` | `BundlesController::items()` | Staff-auth only. |
| `wp_ajax_arm_vehicle_crud` | `includes/public/Customer_Dashboard.php` | Create/update/delete vehicles from customer dashboard. | `POST /dashboard/vehicles` (create/update), `DELETE /dashboard/vehicles/{id}` | `DashboardController::vehicles()` | Customer-auth via session/CSRF. |
| `wp_ajax_arm_submit_estimate`, `wp_ajax_nopriv_arm_submit_estimate` | `includes/public/{Ajax_Submit,class-ajax_submit}.php` | Handle public estimate form submissions. | `POST /api/estimates` | `EstimatesController::submit()` | Public with CSRF hidden field; returns JSON. |
| `wp_ajax_arm_get_vehicle_options`, `wp_ajax_nopriv_arm_get_vehicle_options` | `includes/public/{Shortcode_Form,class-shortcode_form}.php` | Dependent dropdown data for vehicle selection. | `POST /api/vehicles/options` | `EstimatesController::vehicleOptions()` | Public with CSRF; rate-limited. |
| `wp_ajax_arm_partstech_vin`, `wp_ajax_arm_partstech_search` | `includes/integrations/PartsTech.php` | PartsTech VIN decode and catalog search. | `POST /api/partstech/vin`, `POST /api/partstech/search` | `PartsTechController::vin()`, `PartsTechController::search()` | Staff-auth; verifies PartsTech API key. |
| `wp_ajax_arm_get_credit_account`, `wp_ajax_arm_get_credit_transactions` | `includes/credit/Controller.php` | Fetch store credit account and ledger. | `GET /api/credit/account`, `GET /api/credit/transactions` | `CreditController::account()`, `CreditController::transactions()` | Customer-auth; CSRF on mutations. |
| `wp_ajax_arm_customer_credit_history`, `wp_ajax_arm_submit_payment` | `includes/credit/Frontend.php` | Customer credit history + payment submission. | `GET /dashboard/credit/history`, `POST /dashboard/credit/payment` | `CreditController::history()`, `CreditController::submitPayment()` | Customer-auth; CSRF token on POST. |

## Shortcode inventory and page routes

| Shortcode | Module / file | Current output | New route | Controller/view responsibility |
| --- | --- | --- | --- | --- |
| `arm_repair_estimate_form` | `includes/public/{Shortcode_Form,class-shortcode_form}.php` | Public estimate request form. | `GET /estimates/new` | `EstimatesController::form()` renders Twig/Blade view based on `templates/estimate-view.php` with assets from `/assets/`. |
| `arm_customer_dashboard` | `includes/public/Customer_Dashboard.php` | Customer dashboard (vehicles, jobs, requests). | `GET /dashboard` | `DashboardController::index()` renders dashboard template; POST actions routed to JSON endpoints above. |
| `arm_appointment_form` | `includes/appointments/Frontend.php` | Public appointment booking form. | `GET /appointments/new` | `AppointmentsController::form()` rendering existing appointment form template under new view layer. |
| `arm_re_inspection_form` | `includes/inspections/PublicView.php` | Inspection capture form. | `GET /inspections/new` | `InspectionsController::form()` renders existing inspection template. |
| `arm_warranty_claims` | `includes/customer/WarrantyClaims.php` | Warranty claim submission. | `GET /warranty/claim` | `WarrantyController::form()` renders `templates/warranty-claims-test-page.php`. |
| `arm_credit_account` | `includes/credit/Frontend.php` | Customer credit account summary. | `GET /dashboard/credit` | `CreditController::summary()` renders dashboard credit view. |
| `arm_payment_form` | `includes/credit/Frontend.php` | Front-end payment form. | `GET /dashboard/credit/pay` | `CreditController::paymentForm()` renders payment view with Stripe/PayPal widgets. |
| `arm_technician_time` | `includes/timelogs/Shortcode.php` | Technician time tracking portal. | `GET /technician/time` | `TimeLogsController::portal()` gating access; renders technician portal. |

## Form and view layer notes

* Controllers should expose paired methods: `form()` (renders Twig/Blade template) and `submit()` (returns JSON). The renderer can wrap existing PHP templates under `templates/` until Twig/Blade conversion completes; expose an `asset()` helper so scripts/styles continue to load from `/assets/...`.
* Public forms (estimate, appointment, warranty, inspection) must embed CSRF tokens from the new `AuthService` (`wp_create_nonce` shim) and verify via `AuthService::csrf()->verify()` in POST handlers, mirroring the previous `check_ajax_referer` calls.
* Customer dashboard and credit flows should preserve `ARM\Public` outputs: reuse dashboard widgets and vehicle forms, but move submission endpoints to `/dashboard/*` JSON routes with CSRF validation and session-authenticated users.

## Payment and webhook surfaces

| Integration | Existing surface | Public route | Controller expectations |
| --- | --- | --- | --- |
| Stripe | `includes/payments/StripeController.php` REST routes for checkout, payment intent, webhook. | `POST /api/payments/stripe/checkout`, `POST /api/payments/stripe/payment-intent`, `POST /api/webhooks/stripe` | Expose without login; verify Stripe signature on webhook; require configured keys. |
| PayPal | `includes/payments/PayPalController.php` REST routes for order, capture, webhook. | `POST /api/payments/paypal/order`, `POST /api/payments/paypal/capture`, `POST /api/webhooks/paypal` | Public endpoints; signature/ID validation per PayPal headers; mark invoices paid. |
| Zoho | Sync only (no public endpoints today). | `POST /api/webhooks/zoho` (placeholder) | Optional webhook receiver if CRM push is enabled; authenticate via shared secret header. |
| PartsTech | AJAX VIN/search actions. | `POST /api/partstech/vin`, `POST /api/partstech/search` | Restrict to authenticated staff; require PartsTech API key present before dispatch. |

## CSRF and authentication strategy

* Reuse `AuthService` CSRF helper introduced in `includes/auth-helpers.php` to issue hidden form tokens and validate on every POST/PUT/PATCH/DELETE route that mutates customer or appointment data.
* Public estimate/appointment routes remain anonymous but **must** include CSRF fields. Dashboard routes rely on session authentication; admin routes (appointments, bundles, customer search) check capability shims via `current_user_can`.
* Webhook and payment routes are intentionally unauthenticated but must validate third-party signatures or shared secrets before mutating invoices or ledgers.
