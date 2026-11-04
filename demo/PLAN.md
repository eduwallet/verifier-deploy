# Demo application — plan

Status: **in progress**. Flow 0 (deferred credential) is built end to end:

- **Back-end** (`app/`): router, session/CSRF/rate limiting, upstream client with redacted trace,
  issuer service (`create-offer`, `check-offer`, offer fetch, deferred list/detail/authorize/deny),
  `private/catalog.php`, PHPUnit tests.
- **Front-end** (`frontend/`): Vue 3 + Vite + TypeScript + PrimeVue, hash router, five-step wizard for
  flow 0 (`src/flows/deferred/`), debug panels (timeline, back-end <-> issuer exchanges, session state),
  Vitest tests. Walked through in a headless browser against a fake issuer.

Not yet done: verification against the real sandbox issuer (needs the `DeferredCredential`
configuration deployed), the Ansible entry and `hasFrontend` build step, the verifier calls, other flows.

## 1. Goal

A demo application that shows end-to-end credential flows against the live
issuer and verifier agents. It sits next to `demo-cv` in this repository, but is
a more complete and structured demonstrator:

- **Back-end (PHP)**: thin proxy/interface to the live issuer and verifier. It is
  the only component that knows the API tokens; they are stored server-side only.
- **Front-end (Vue + PrimeVue)**: talks exclusively to the back-end. It guides the
  visitor through a number of flows (issue, verify, combinations).

## 2. Starting point: what demo-cv already does

Findings from `demo-cv` that we reuse or deliberately change:

| Aspect | demo-cv | demo (proposal) |
|---|---|---|
| Runtime | `php:8.2-apache` container | same base image, keep deployment model |
| Docroot split | `app/html` (public) and `app/private` (`.env`, config) mounted separately | keep: tokens never inside the docroot |
| Tokens | `.env` with `ISSUER_{ENV}_{NAME}` / `VERIFIER_{ENV}_{NAME}` variables, mapped by `setTokens.php` | keep the naming convention, so existing `.env` files can be reused |
| Back-end | one script `backendcall.php` with `action=create\|state` | a small router with explicit endpoints (see section 4) |
| Session auth | session `token` compared with POST `token` | session-based, plus CSRF token on state-changing calls |
| Front-end | plain `index.php` + jQuery-style ajax | Vue 3 SPA with PrimeVue |
| Config | `config.php` mixes credential payloads and endpoints | split into endpoint config and a catalog of building blocks (credentials, presentations); flows live in the front-end |

Upstream API surface used by the back-end (from `demo-cv` and `veramo-verifier/README.md`):

- Issuer: `POST https://{tool}.{domain}/{short}/api/create-offer`, `POST .../api/check-offer` (body `{id}`)
- Verifier: `POST .../{instance}/api/create-offer/{presentationid}`,
  `POST .../{instance}/api/create-dcql-offer`, `GET .../{instance}/api/check-offer/{state}`,
  `.../api/check-status`
- Authentication to both: `Authorization: Bearer <admin token>`

## 3. Architecture

```
Browser (Vue SPA)  ──JSON──►  PHP back-end  ──Bearer token──►  Issuer / Verifier (live)
        ▲                          │
        └── no tokens, no upstream URLs ◄── keeps tokens + session state server-side
```

Principles:

1. The front-end never sees a token, an upstream URL or an upstream raw response.
   The back-end returns a normalised, minimal JSON model (`offerUri`, `qrData`,
   `status`, `claims`).
2. The front-end cannot choose arbitrary upstream targets. It sends symbolic keys
   (`flow`, `step`); the back-end resolves these through a server-side allowlist.
3. The back-end offers small, generic building blocks (issue, verify, poll, result).
   Flow logic (ordering, branching, combining) is code in the front-end (see section 5).
   The back-end only enforces what each building block may do.

## 4. Back-end design

### 4.1 Layout

```
demo/
  PLAN.md
  docker/            Dockerfile (php-apache), docker-compose.yml (local development only)
  app/
    composer.json
    html/            docroot: index.php (router), built SPA assets (build output, git-ignored)
    src/             Controller/, Service/, Http/ (upstream client), Config/
    private/         .env, catalog.php, setTokens (outside docroot, mounted separately)
    tests/           PHPUnit
  frontend/          Vue project (Vite), builds into app/html
```

### 4.2 Proposed API (front-end facing)

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/catalog` | building blocks the front-end may use: credential types, presentation requests, grant types (no secrets) |
| POST | `/api/issue` | create one issuance offer containing one **or several** credentials |
| POST | `/api/verify` | create an authorization request (verifier) for a presentation request or DCQL query |
| GET | `/api/session/{id}` | poll status of an issue/verify session |
| GET | `/api/session/{id}/result` | verified claims / issuance summary once finished; verified claims can be referenced by a later `issue` call |
| POST | `/api/session/{id}/cancel` | abandon a session |
| GET | `/api/session/{id}/offer` | fetch and return the credential offer JSON for this session (see 5.1) |
| GET | `/api/session/{id}/deferred` | open deferred transactions of the issuer, enriched, own one flagged |
| POST | `/api/session/{id}/deferred/authorize` | release the session's own deferred credential with claims |
| POST | `/api/session/{id}/deferred/deny` | deny the session's own deferred credential (optional reason) |

Every response carries, next to the normalised model, a `trace` array for the debug
panels (see 6.1): each upstream exchange made for that call, as `{ step, method, url,
requestHeaders, requestBody, status, responseHeaders, responseBody, durationMs }`. Bearer
tokens and other secrets are **redacted by the back-end** (`Authorization: Bearer ***`);
the front-end never receives them. The trace is limited to the dev environment and can be
switched off by a setting.

The status call returns both the raw issuer `status` (the wizard maps steps on this) and a normalised
`phase`: `created`, `retrieved`, `token`, `processing`, `pending`, `ready`, `denied`, `issued`, `expired`
or `error`. Status values are normalised: `created`, `processing`, `finished`, `error`, `expired`
(mapping the issuer's `OFFER_CREATED`, `ACCESS_TOKEN_*`, `CREDENTIAL_ISSUED`, ... as
`demo-cv` already does).

### 4.3 Security requirements

- Tokens only in `private/.env`; never logged or included in the debug trace (demo-cv currently logs request
  bodies and responses; we log metadata only).
- Allowlist-based resolution of group/issuer/credential; reject everything else.
- CSRF token + same-origin only (`SameSite=Strict`, no permissive CORS).
- Per-session rate limiting on create-offer calls (live agents are being driven).
- Server-side session state per flow run; the browser only holds an opaque session id.
- cURL with TLS verification on, sane timeouts, upstream errors mapped to generic
  front-end errors.

### 4.4 Credential callback support

`demo-cv` supports `credential_callback` (the issuer calls back for the credential
data, authenticated by `token=callback`). The new back-end should give this a
dedicated, separately-authenticated route with a per-session one-time secret
instead of the shared literal `callback` token.

## 5. Flow model

Flows are too complex to describe as declarative data: they combine issuing and
verifying in one run, issue several credentials in one offer, and may branch on
the outcome of an earlier step. Therefore **flows are coded in the front-end**
(TypeScript composables plus one Vue page per flow), built on a small typed API
client for the building blocks in section 4.2.

Division of responsibility:

- **Front-end** — owns the flow: which steps, in which order, what is shown, what
  happens on error or timeout. Each flow is a module in `frontend/src/flows/`, for
  example `issueAndVerify.ts`, `multiIssue.ts`.
- **Back-end** — owns what is *allowed*: `private/catalog.php` lists the credential
  types (with their credential id, grant type and data template), presentation
  requests and upstream targets. A request outside the catalog is rejected.

Points that the building blocks must support:

1. **Multi-credential offers**: `/api/issue` takes a list of catalog keys and sends
   them as the `credentials` array of one `create-offer` call (demo-cv already sends
   an array; to be confirmed that the live issuer supports more than one entry per offer
   for all grant types).
2. **Verify then issue**: the result of a verify session is kept server-side. A later
   `issue` call may reference it (`fromSession`) so claims from the presentation feed
   `credentialDataSupplierInput`. The front-end never passes raw claims back to the
   back-end, since that would let a visitor issue arbitrary data; the back-end maps
   verified claims to the template in the catalog.
3. **Sequencing/state**: a flow run can own several sessions; sessions are grouped
   under a `run` id so one can be cancelled or cleaned up as a whole.

Candidate flows (flow 0 is the first one to implement and is worked out in 5.1):

0. **Deferred issuance** (sandbox issuer, `DeferredCredential`).
1. **Issue, pre-authorized**: wallet scans QR, credential is issued.
2. **Issue, authorization code**: with login at the issuer.
3. **Multi-issue**: several credentials in one offer.
4. **Verify**: request a presentation of one credential type (PID, ABC, edubadge).
5. **DCQL verify**: ad-hoc query via `create-dcql-offer`, e.g. selective disclosure.
6. **Verify then issue**: present a PID, receive a credential based on its claims.
7. **Issue then verify**: guided round trip with the freshly issued credential.
8. **Status check**: revocation/suspension via `check-status`.

### 5.1 Flow 0: deferred credential (first flow)

A wizard (PrimeVue `Stepper` with numbered steps) that follows one deferred issuance from
offer to issued credential on the **sandbox issuer**, including the back-office role
(the demo itself approves the credential). Every step shows what the user must do, what
technically happens, and the current status (see 6.1 for the panels).

#### Upstream facts this is based on

Checked against `veramo-agent` (README and `src`), not assumed:

- `POST {issuer}/api/create-offer` returns `{ uri, id }`; `id` is the session/transaction id and
  is also the id used by `check-offer` and by `/api/v2/deferred/:id`. This is how the demo
  knows which pending transaction is "its own".
- `POST {issuer}/api/check-offer {id}` returns `status`, `createdAt`, `lastUpdatedAt`, `expiresAt`
  (deferred only), `reason`, `requests`. Statuses used: `OFFER_CREATED`, `OFFER_URI_RETRIEVED`,
  `ACCESS_TOKEN_CREATED`, `CREDENTIAL_PENDING`, `CREDENTIAL_READY`, `CREDENTIAL_DENIED`,
  `CREDENTIAL_ISSUED`.
- `requests` holds the last request/response per protocol phase, in this natural order:
  `create_offer-request/-response`, `credential_offer-request/-response`,
  `access_token-request/-response`, `get_credential-request/-response` (the 202 answer),
  `deferred-request/-response` (overwritten by every wallet poll, so only the latest is kept),
  `deferred-denied`, and `notification-request` (the wallet's notification, if it sends one).
- `GET {issuer}/api/v2/deferred` lists open transactions as `{ id, credential_configuration_id,
  status, createdAt, expiresAt }` only: **no claims**. `GET .../deferred/:id` gives `format`,
  `proofs`, `ttl`, `interval`, `data` (from the offer), `claims_supplied` and `claims[]`
  (`path`, `type`, `mandatory`, `origin`, `supply`). `POST .../deferred/:id` with
  `{ claims, status?, reason? }` releases or denies the credential.
- The wallet collects the credential later at `{issuer}/deferred`, at its own polling `interval`.

#### Deviations from the original description (confirmed)

1. **Endpoint name**: the approve call is `POST /api/v2/deferred/:id`, not `/api/v2/issue/:id`.
2. **Claim fields**: the issuer accepts `claims` in the approve call only for claims that the
   credential configuration reads from `#deferred` (or `#any`); other names give a `400`. So the
   `DeferredCredential` configuration *must* declare `name`, `email`, `institute` with such an origin
   (and the issuer in fact requires an explicit `origin` for every claim of a deferred credential).
   Consequence: the detail endpoint then *does* list these three claims in `claims[]`, and the
   wizard can build its input fields from that list (with defaults for known names) instead of
   hard-coding them. This is better than the current assumption that the offer doesn't know them.
3. **Step-1 data**: the values entered in step 1 are sent as plain key/value pairs in
   `credentialDataSupplierInput` (no origin there: an `origin` can only be set in the credential
   configuration, which is where `#any` is declared). Because the configuration reads `#any`, the
   values from step 4 take priority over these offer values. The pending-transaction detail shows the
   offer values under `data`, and step 4 pre-fills from them. This demonstrates the offer/back-office
   override mechanism.
4. **Accept moment** (confirmed): according to the issuer README, the wallet asks the user to accept between
   `OFFER_URI_RETRIEVED` and `ACCESS_TOKEN_CREATED`, i.e. *before* the status leaves
   `OFFER_URI_RETRIEVED`. The "please accept in your wallet" hint is therefore shown in step 2 as soon as
   `OFFER_URI_RETRIEVED` is seen; step 3 explains and waits for the wallet's credential request
   (token -> credential request -> `202` with `transaction_id`).

#### Steps

| # | Wizard step | What the user sees / does | Back-end calls | Advance when |
|---|---|---|---|---|
| 1 | **Prepare** | inputs `name` (`Demo User`), `email` (`demouser@example.com`), `institute` (`Example Inc`), large **Issue** button | `POST /api/issue` -> issuer `create-offer` | offer created |
| 2 | **Offer** | offer URI (copyable + same-device deeplink), QR code, offer JSON in a `<pre>` (fetched by the back-end), live status; hint to accept in the wallet once `OFFER_URI_RETRIEVED` | `GET /api/session/{id}/offer`, polling `GET /api/session/{id}` (issuer `check-offer`) every 1 s | status is not `OFFER_CREATED`/`OFFER_URI_RETRIEVED` |
| 3 | **Wallet request** | explanation that the wallet has accepted, got a token, requests the credential and receives `202`; shows the `access_token-*` and `get_credential-*` exchanges | polling as above | `CREDENTIAL_PENDING` |
| 4 | **Back-office** | table of open deferred transactions (own row highlighted), detail card of the own transaction (id, ttl, interval, expiry, credential configuration, claim definitions, data known), claim inputs, large **Authorize** button (and a smaller **Deny**) | `GET /api/session/{id}/deferred`, `POST .../authorize` -> issuer `POST /api/v2/deferred/:id` | authorize returned `200` |
| 5 | **Issued** | status, ordered `requests` as JSON, notification if present, final result | polling every 1 s; every 10 s once `CREDENTIAL_ISSUED` | n/a: stops on expiry, deny, leaving the page |

Step 4 table (open transactions): columns are **short id** (first 8 chars, full id in a
tooltip), **credential** (display name), **status**, **created** and **expires** (relative, such as
"in 59 min", with the exact local time in a tooltip), **claims known** (tag per claim name that
`data`/`claims_supplied` already contains, e.g. `name`, `email`), **proofs** (number of holder keys) and an
"own" badge. The back-end builds this by listing, then calling the detail endpoint for each item
of the `DeferredCredential` configuration (the list endpoint has no claims; the number of
details fetched is capped).

Terminal/edge states handled by the wizard: `CREDENTIAL_DENIED` (shows the `reason`), expired
(`expiresAt` passed), polling or upstream errors (shown, retried with back-off), the user leaving the
page (polling stops; the run can be resumed from the PHP session while it is open).

#### Back-end rules specific to this flow

- Catalog entry `deferred` -> sandbox issuer, credential id `DeferredCredential`, pre-authorized grant
  without `tx_code`. Allowed input keys: `name`, `email`, `institute` (strings, length-limited).
- `authorize`/`deny` only work for the offer id that was created in this PHP session; the other pending
  transactions of the shared sandbox are shown read-only. Claims are restricted to the catalog keys.
- `GET /api/session/{id}/offer`: the back-end parses the `credential_offer_uri` out of the offer URI
  (or reads an inline `credential_offer`) and fetches it, but only from the configured issuer host, so
  it cannot be used as a request relay.
- The `trace` (6.1) includes these upstream calls. The polling at 1 req/s per session must stay within
  the per-session rate limit of 4.3; polling calls are left out of the trace beyond the last few to keep
  responses small.

#### Front-end structure for this flow

- `frontend/src/flows/deferred/`: `useDeferredFlow.ts` (a small state machine with the
  status -> step mapping and the poll schedule), one component per step, shared `StatusBadge`,
  `JsonPanel`, `QrPanel` components.
- Current step is derived from the latest known issuer status (monotonic), so a page reload or a status
  that jumps over a step (e.g. `ACCESS_TOKEN_CREATED` and `CREDENTIAL_PENDING` within one poll)
  lands in the right step.
- QR rendering client-side with a small library (e.g. `qrcode`), no external service: the offer URI
  never leaves the system.

#### Configuration change: `agents-conf` (sandbox issuer)

The sandbox issuer cannot issue `DeferredCredential` yet (no credential configuration, no `deferred`
attribute anywhere in `agents-conf`). Proposal, modelled on the existing `GenericCredential_JWT`:

- Add `issuer/sandbox/credentials/DeferredCredential.json`: format `vc+jwt` (same binding and
  proof settings as `GenericCredential_JWT`), `type: ["VerifiableCredential", "DeferredCredential"]`,
  `credentialSubject` with `name`, `email`, `institute`, each with `origin: [["#any", "<claim>"]]`,
  and `"deferred": { "ttl": 3600, "interval": 5 }` (short interval so the demo reacts quickly;
  the issuer default would be 72 s, or at least 10 s).
- Add the matching entry (display names/colours) to `issuer/sandbox/metadata/sandbox.json`.
- No code change in `veramo-agent` expected: unknown types fall back to `GenericCredential`
  (`CredentialFactory`). If the display or the SD-JWT variant (`dc+sd-jwt`) is wanted, add that as a
  second configuration later.
- `agents-conf` is currently on the `release` branch; per its README the dev/poc servers use the
  **`development`** branch, so the change goes there.
- The issuer image must contain the deferred support (`veramo-agent` commit `05baee7`,
  2026-11-03 and later) and be redeployed with the new configuration. The deferred issuer
  fails to start on a wrong deferred configuration, which makes mistakes visible directly.

## 6. Front-end design

- Vue 3 + Vite + TypeScript, PrimeVue (stock Aura preset), Pinia for
  flow/session state, Vue Router for the flow pages.
- Main components: flow picker (cards), stepper (PrimeVue `Stepper`), QR/deeplink
  panel with same-device button, live status (polling, with a back-off), result
  view (claims table, raw JSON in a collapsible panel), error/timeout states.
- Sessions are plain PHP sessions (file based); sufficient for a single container.
- Polling `GET /api/session/{id}` every ~2 s with a hard timeout; SSE is possible
  later but not needed for a first version.
- Interface language is English only, with no i18n layer for now. No branding yet: the stock PrimeVue theme (Aura) is used, with theme tokens kept in one place so branding can be added later.
- Accessible by default (keyboard, focus, contrast); responsive for phone use,
  since the wallet is often on the same device.
- The build output goes into `app/html/assets`, served by Apache; no Node at runtime.

### 6.1 Technical detail and debug panels

The purpose of the demo is to make each flow followable step by step, so every
page shows technical detail next to the user-facing steps, as PrimeVue panels
(`Panel`/`Accordion`, `Splitter` with the stepper on one side and details on the other):

- **Timeline/log panel**: chronological list of events in the run (offer created,
  offer retrieved by the wallet, token requested, credential issued, presentation received,
  ...), each with a timestamp; the active step highlighted.
- **HTTP exchange panel**: the back-end <-> issuer/verifier calls from `trace`
  (method, URL, redacted headers, request and response bodies, status, duration),
  with syntax-highlighted JSON and copy buttons.
- **Offer/request panel**: the raw credential offer URI or authorization request URI, its
  decoded parameters, and (where available) the decoded request object / JWT with
  header and payload shown separately.
- **Credential/presentation panel**: the issued or presented credential decoded
  (SD-JWT disclosures, mdoc claims or JSON-LD as applicable) and the claims table.
- **State panel**: the normalised session state and each raw upstream status mapped to
  the normalised one.
- Panels are collapsible, remember their open/closed state, and a global
  "show technical details" toggle hides them for a clean presentation.

## 7. Environments and deployment

- This application is for **development only**: it targets the `dev` environment
  (`ISSUER_DEV_*` / `VERIFIER_DEV_*` tokens). The environment is fixed per deployment
  and not selectable by the visitor; test/stage/prod tokens are not loaded.
- **Deployment goes through the Ansible setup in `agent/ansible`**, onto `poc5.eduwallet.nl`
  (inventory `environments/eduwallet`). `demo-cv` is already installed this way via
  `group_vars/eduwallet/35-verifierdeploy.yml` (`verifierdeploy.installations`: name, port,
  domain, path, templates such as `env.test.j2` -> `app/private/.env`). The new
  application is added as a second installation entry:
  - `name: demo`, `path: demo`, **port `8002`**, **domain `demo.dev.eduwallet.nl`** (DNS to be created).
  - An `.env` template for the dev tokens, rendered from the vault like `env.test.j2`.
- **How Ansible builds the container** (read from `roles/copy_files/templates/docker-compose.yml.j2` and
  `roles/post_config/tasks/installtasks.yml`), which dictates the repository layout:
  - the generated compose service builds with `docker/` as build context and `docker/Dockerfile`,
    and mounts `<path>/app` as `/var/www`. The `docker/docker-compose.yml` in the repository is used only
    for local development. So the Apache docroot must stay `app/html` (`/var/www/html`), and
    `app/private` and `app/vendor` are outside the docroot, as in `demo-cv`.
  - because the Docker build context is `docker/`, a multi-stage Dockerfile **cannot** reach `../frontend`.
    Therefore the SPA is built the same way composer is installed for `demo-cv` (a `docker run` of a
    tool image against the `app` directory, enabled by a flag like `hasComposer`): a new post-config
    step, flagged e.g. `hasFrontend`, runs `node:<lts> npm ci && npm run build` on `frontend/` with output in
    `app/html`. The server needs Node only inside that one-off container, never at runtime.
    This needs a small change to the `post_config` role (`installtasks.yml`), made in the
    `agent/ansible` repository.
  - `hasComposer: true` for the PHP dependencies, same as the existing flow (`composer.json` in `app/`).
- **Traefik**: `traefik.enabled` makes the template add `traefik.http.routers.demo...rule=Host(...)`
  labels, with TLS (`websecure`, Let's Encrypt resolver) terminated at Traefik. The container
  itself only speaks plain HTTP on port 80 (published locally as 8002). Consequences for the PHP
  code: the request looks like `http` to Apache, so the session cookie `Secure` flag comes from a
  setting (`.env`: true on poc5, false for local development) and not from detecting HTTPS;
  `X-Forwarded-*` headers are only trusted for the logged client address and not used for security
  decisions or URL building. The browser-facing base URL is a configuration value.
- **Single origin**: the SPA and the PHP API are served from the same host
  (`https://demo.dev.eduwallet.nl/` for the front-end, `/api/...` for the back-end), so there
  is no CORS configuration and the session cookie is first-party (`HttpOnly`,
  `SameSite=Strict`). The Vite build uses relative URLs, so the hostname does not matter
  at build time.
- The back-end calls the sandbox issuer on its public URL, as `demo-cv` does
  (`https://sandbox.<domain>/api/...`); the deferred flow needs no inbound callback to the demo.
- `.gitignore`: `.env`, `vendor`, `node_modules`, and the SPA build output in `app/html`.
- Local development: `docker/docker-compose.yml` on a port such as `8081`, plus the Vite dev
  server with a proxy to the PHP container.
- README section for `demo` added next to the `Demo-CV` section.

## 8. Milestones

1. **Scaffold**: directory layout, Dockerfile/compose, composer + Vite projects,
   "hello" endpoint reachable from the SPA through the container.
2. **Back-end core**: minimal plain-PHP router, config/token loading, catalog allowlist,
   upstream HTTP client that records a redacted `trace`, PHP session state, `/api/catalog`.
3. **Configuration + issue (flow 0, steps 1-3)**: `DeferredCredential` in `agents-conf`
   (`development` branch) and deployed on the dev sandbox issuer; wizard skeleton with steps 1-3,
   `/api/issue`, `/api/session/{id}`, `/api/session/{id}/offer`, QR, debug panels.
3b. **Back-office and completion (flow 0, steps 4-5)**: pending list with details, Authorize/Deny,
   final polling with notification display, ordered `requests` JSON.
4. **Verify flow** end-to-end: `/api/verify`, result view with claims.
   Debug panels (6.1) are built from milestone 3 onward, not added afterwards.
5. **Complex flows**: multi-credential issue, verify-then-issue, issue-then-verify,
   authorization code, DCQL, status check.
6. **Hardening**: CSRF, rate limiting, log hygiene, error mapping, timeouts, tests
   (PHPUnit for the back-end with a mocked upstream; Vitest for stores/components).
7. **Polish and docs**: accessibility pass, README, deployment notes.

Each milestone should be independently runnable and reviewable.

## 9. Decisions taken

- Flows are coded in the front-end; the back-end exposes building blocks plus a catalog allowlist.
- Development environment only; fixed per deployment.
- Plain PHP with a minimal router, no framework.
- No branding for now; interface in English only.
- PHP file sessions for session state.
- Debug/technical-detail panels are a core feature, fed by a redacted `trace` from the back-end.
- Deployment via `agent/ansible` on `poc5.eduwallet.nl`: hostname `demo.dev.eduwallet.nl`, port 8002,
  proxied by Traefik which terminates TLS; SPA and API served from the same origin.
- Flow order: (0) deferred credential, (next) multiple-credential issuance, (third, probably)
  verify-then-issue.
- Flow 0 as in 5.1: endpoint `POST /api/v2/deferred/:id`; `DeferredCredential` declares `name`, `email`,
  `institute` with `#any` origin in the credential configuration; step-1 data goes in
  `credentialDataSupplierInput` as plain values; accept hint is shown in step 2.
- Wallet for testing: probably Paradym or Siros ID; not a concern for now.

## 10. Open questions / later

1. **Multi-credential issuance** (probably flow 1): needs changes in `veramo-agent` first (and in the
   dev issuer). Until then it is blocked; the API accepts a list from the start so the front-end
   does not change later. The changes still have to be specified.
2. **Verify-then-issue** (probably flow 2): no concrete example yet; the `fromSession` claim mapping
   is designed when that flow comes up.
3. The dev issuer must be updated to a `veramo-agent` version with the deferred endpoints
   (commit `05baee7`) and the `DeferredCredential` configuration from `agents-conf` before flow 0 works.
4. `post_config` needs a frontend build step (`hasFrontend`) and the `verifierdeploy.installations`
   entry must be added in `agent/ansible`; DNS for `demo.dev.eduwallet.nl` must exist.
5. Does the notification endpoint get called by Paradym or Siros ID for deferred credentials? Not a
   blocker: the step 5 panel shows `notification-request` when present.
