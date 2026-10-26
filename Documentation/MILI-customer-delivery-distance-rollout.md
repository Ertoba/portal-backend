# MILI trusted delivery distance — rollout checklist

Customer checkout-summary, place-order and prescription place-order now overwrite
client `distance` using server-side Google Routes Compute Routes. Parcel routes
run from the sender's pickup coordinates to the recipient in `receiver_details`.
Normal delivery routes run from the DB store coordinates to customer coordinates.

## Before deployment
1. Enable **Routes API** in Google Cloud for the billing project.
2. Create a **server-only** API key restricted to Routes API; apply the
   appropriate network/IP restrictions where supported. Do not commit the key.
3. Set `GOOGLE_ROUTES_API_KEY` in the backend production environment (do not
   print its value in tickets/logs). Rebuild Laravel config cache in a controlled
   release so `config('services.google_routes.key')` is available.
4. Confirm the API account, quotas, billing and outbound HTTPS connectivity.
5. On staging, test delivery and parcel checkout summary and actual placement
   with changed client distance (0, 0.1, 10000): server must charge the same
   trusted-route price. Confirm a provider outage fails 503 and does not place
   an undercharged order. Test take-away remains unaffected.
6. Confirm existing mobile clients handle errors and that rate-limiting/
   budgeting is adequate: route results are cached for five minutes.

## Important
- Do not deploy without configuring the key: route-dependent checkout deliberately
  returns 503 rather than silently trusting customer distance.
- This protects CUSTOMER order placement and checkout summary. POS, Builder,
  order-edit, and other direct callers of DeliveryChargeService need separate
  entry-point verification and integration testing before global certification.
- No production deploy, migration or live payment was run as part of this change.

## Admin / Vendor POS pricing hardening
- Saving a delivery address now measures the driving route from the DB store
  coordinates to the pinned destination, ignoring hidden input `distance`.
- Final POS order creation recomputes that route again before vehicle selection,
  delivery pricing and persisting the delivery address. Take-away/POS without a
  delivery address is unaffected in Vendor POS.
- The map's initial extra-charge AJAX preview still uses a browser-supplied
  distance before the address has been saved. Treat it as a non-authoritative
  preview; the saved-address quote and final order now use server-verified
  route distance. The initial map quote itself must be harmonized separately
  for full UI parity.
- Staging acceptance: both Admin/Vendor POS, manual `distance=0` and altered
  session distance, several destination pins, delivery vs pickup, absent provider
  key, provider outages, vehicle coverage, and fee preview vs final invoice.

## POS initial map-preview parity
- The Admin/Vendor `extra_charge` AJAX price quote now sends pinned
  latitude/longitude; the server ignores `distancMileResult` and calls the
  SAME route verifier used by saved addresses and completed POS orders.
- Coverage-picker re-quotes also include pinned coordinates. Without valid
  coordinates, the endpoint rejects the quote rather than treating the
  missing client distance as 1 km.
- Browser quote failure clears the fee display instead of showing a
  misleading 0/free delivery charge.
- This is a breaking contract for third-party POS quote callers that omit
  coordinates; review integrations and test both POS interfaces in staging.
