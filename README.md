# Bring a Buyer

Affiliate / referral program built with **Symfony 7.4**. Partners create referral links, admins manage partner stores, and partner websites track visits via a JS tracker and API.

## Features

### Public area
- Home page with registration CTA
- User registration and login
- UI locale: **Ukrainian** (`translations/messages.uk.yaml`)

### Partner cabinet (`/cabinet`)
- **Account details** — profile and balance
- **Advertising links** — create / view / delete
  - partner select from admin-created partners
  - link key with availability check / random generation
  - URL format: `{partner url}/pr/{key}`
  - delete allowed only when there are no clicks, orders, or rewards
- **Financial operations** — accruals and withdrawals history (sample data)

### Admin cabinet (`/admin_9fg!lp`)
- Separate login and separate `AdminUser` entity (not related to `User`)
- Independent browser sessions:
  - `PARTNER_USER_SESSID` — partner user
  - `PARTNER_ADMIN_SESSID` — admin  
  Both cabinets can stay logged in in different tabs of the same browser.
- **Our partners** — create, edit, delete
  - a partner can be deleted only if no advertising links exist for them in any account

### Tracking on partner websites
- Script: `public/js/partner-tracker.js`
- API: `POST /api/views` — register a visit
- Entity: `LinkView` — visit history (`advertisingLink` + `createdAt`)
- Cookies on the partner domain for order attribution:
  - `bb_ref` — link key
  - `bb_ref_id` — advertising link id

## Stack

- PHP ≥ 8.2
- Symfony 7.4
- Doctrine ORM
- Twig + Asset Mapper + Stimulus
- MySQL 8 (`DATABASE_URL` in `.env`, port 3309)

## Quick start

```bash
composer install
# .env DATABASE_URL (MySQL 8 on port 3309):
# mysql://root:root@127.0.0.1:3309/kwadro_partner?serverVersion=8.0.34&charset=utf8mb4
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
php bin/console app:create-admin --email=admin@example.com --password=admin123
php -S 127.0.0.1:8000 -t public
```

Open: http://127.0.0.1:8000

### Useful CLI commands

```bash
# create / update an administrator
php bin/console app:create-admin --email=admin@example.com --password=secret123

# (optional) legacy PartnerSite demo seed
php bin/console app:seed-partner-sites
```

## Main URLs

| Area | URL |
|---|---|
| Home | `/` |
| User registration | `/register` |
| User login | `/login` |
| Partner cabinet | `/cabinet` |
| Admin login | `/admin_9fg!lp/login` |
| Admin cabinet | `/admin_9fg!lp` |
| Partners (admin) | `/admin_9fg!lp/partners` |
| Views API | `POST /api/views` |
| JS tracker | `/js/partner-tracker.js` |

## Data model (core)

- **User** — program participant
- **AdminUser** — administrator (separate `admin_users` table)
- **Partner** — partner store managed by admin: name, URL, email, phone
- **AdvertisingLink** — account referral link (User + Partner + `linkKey`)
- **LinkView** — link visit (AdvertisingLink + `createdAt`)
- **PartnerSite** — legacy seeded catalog (not used for new links)

## Referral link format

```text
{Partner.url}/pr/{linkKey}
```

Example: `https://shop.example.com/pr/ab24jc3yts`

## Views API

`POST /api/views`  
`Content-Type: application/json`  
Public endpoint (CORS enabled for partner-site browser calls).

```json
{ "key": "ab24jc3yts" }
```

or

```json
{ "url": "https://shop.example.com/pr/ab24jc3yts" }
```

Success (`201`):

```json
{
  "ok": true,
  "view": {
    "id": 1,
    "advertisingLinkId": 1,
    "createdAt": "2026-11-14T17:01:17+00:00"
  }
}
```

Also increments `clicks` on the related `AdvertisingLink`.

## Partner website tracker

```html
<script src="https://YOUR-DOMAIN/js/partner-tracker.js" async></script>
```

The script:

1. Reads the key from `/pr/{key}` or query (`?pr=` / `?ref=`)
2. Sends a visit to `/api/views`
3. Stores `bb_ref` / `bb_ref_id` cookies for 30 days (`SameSite=Lax`)

When placing an order on the partner site:

```js
const key = window.BringBuyer.getReferralKey();
const linkId = window.BringBuyer.getAdvertisingLinkId();
```

Duplicate view requests in the same tab are blocked with `sessionStorage`.

## Security and sessions

- Firewall `main` — participants (`User`)
- Firewall `admin` — administrators (`AdminUser`), pattern `^/admin_9fg!lp`
- Separate session and remember-me cookies so auth contexts do not collide
- Admin URL is intentionally non-obvious: `/admin_9fg!lp`

## Project structure (short)

```text
src/
  Controller/          # public, cabinet, admin, api
  Entity/              # User, AdminUser, Partner, AdvertisingLink, LinkView, …
  Security/            # separate authenticators for user/admin
  Session/             # split session cookie by area
  EventSubscriber/     # CORS for API
public/
  js/partner-tracker.js
translations/
  messages.uk.yaml
  validators.uk.yaml
  security.uk.yaml
```

## Development notes

- UI copy and translation keys live in `translations/`
- Update DB schema in dev: `php bin/console doctrine:schema:update --force`
- Prefer Doctrine Migrations for production schema changes
