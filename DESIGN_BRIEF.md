# Pryvora — Redesign Brief

> **Paste this whole file into Claude as the prompt.** It describes what Pryvora is, exactly how it looks and works today, what's wrong with it, and what to design. Nothing here is aspirational — every detail was read out of the actual codebase.

---

## Your task

Design a **completely new visual identity and UI** for Pryvora, and implement it across the entire application. The current design is a scaffolded, generic dark admin template with no brand identity. I want something deliberate, distinctive, and coherent — a design a human art director would be proud of, not something that reads as "default shadcn dark mode."

Do not preserve the current look. Start from a real aesthetic point of view.

---

## 1. What Pryvora is

**A self-hosted, privacy-first personal management platform.** From the project's own README:

> "Not a simple todo app or an email client. It is a central control panel for personal data — designed to replace scattered tools with a single private system."

The core promise: **"A database breach must not expose readable user data."** Sensitive content is encrypted at rest with AES-256-GCM; passwords use Argon2id. If someone steals the database, they get ciphertext.

**Who it's for:** technically-inclined people who self-host — they own the server, the database, the backups. They've chosen not to trust Google Keep / Todoist / Notion with their private data. Developer/prosumer persona. They value control, precision, and sobriety over playfulness.

**Brand positioning:** a *control panel*, not a cute productivity app. Calm, secure, precise, confident. The emotional target is "everything that matters, in one place, and nobody else can read it."

**The privacy story is real and should be felt in the design, not just stated.** Encryption is genuinely implemented. The search architecture is a hybrid: PostgreSQL stores ciphertext, Elasticsearch indexes only titles as tokens — encrypted note bodies never touch the search index. That's a meaningful, honest constraint worth expressing visually.

**Current maturity:** pre-1.0. Auth + 2FA, Notes, Tasks, and a Dashboard are built. Calendar is backend-complete but **has no UI at all**. Email triage, documents, and notifications are roadmap-only — do not design them.

---

## 2. Tech stack you must work within

| Thing | What it is |
|---|---|
| Framework | React 19.2, Vite 7.3 |
| Router | react-router-dom 7 (`BrowserRouter`, flat route table) |
| Styling | **Tailwind CSS v4** — CSS-first config, *no `tailwind.config.js`*. All theming lives in `src/index.css` via `@import "tailwindcss"` + `@theme` + CSS custom properties |
| Components | **shadcn/ui** ("new-york" style) on **Radix** primitives, in `src/components/ui/` |
| Variants | `class-variance-authority` + `clsx` + `tailwind-merge` (the `cn()` helper in `src/lib/utils.js`) |
| Animation | `framer-motion` v12 — used pervasively and consistently |
| Icons | `lucide-react` |
| Dates | `date-fns`, `react-day-picker` |
| Fonts | Currently Inter (body) + Manrope (display), loaded from Google Fonts in `index.css` |
| State | React Context only (`AuthContext`, `ThemeProvider`) — no Redux/Zustand |

**Code conventions to match:** the codebase uses `snake_case` for variables and functions (e.g. `should_reduce_motion`, `api_request`, `get_page_variants`), with `PascalCase` components. Allman-style braces (opening brace on its own line). Match this — do not reformat to camelCase.

**Ignore `src/remotion/`** — that's a separate promo-video generator bundled in the repo, not part of the web app.

---

## 3. How the app is structured today

### Routes

| Path | File | Auth | Purpose |
|---|---|---|---|
| `/login` | `src/pages/Login.jsx` | public | Sign in **+ inline 2FA challenge** |
| `/register` | `src/pages/Register.jsx` | public | Create account |
| `/dashboard` | `src/pages/Dashboard.jsx` | protected | Home / daily overview |
| `/notes` | `src/pages/Notes.jsx` | protected | Notes CRUD |
| `/tasks` | `src/pages/Tasks.jsx` | protected | Tasks CRUD |
| `/calendar` | `src/pages/Calendar.jsx` | protected | **"Coming soon" stub — 26 lines** |
| `/settings` | `src/pages/Settings.jsx` | protected | 2FA, password, tags |

Guarded by `src/components/ProtectedRoute.jsx`. Note: **2FA is not its own route** — it's a conditional state inside `Login.jsx`, so it isn't URL-addressable.

### App shell

- `src/components/layout/AppLayout.jsx` — `flex h-screen`: fixed sidebar + right column of Topbar over scrollable `<main>`.
- `src/components/layout/Sidebar.jsx` — `w-64`. "Pryvora" wordmark, separator, **text-only nav with no icons** (Dashboard, Notes, Tasks, Calendar, Settings), then "Signed in as {email}" + a ghost Logout button.
- `src/components/layout/Topbar.jsx` — `h-16`, three zones: page title (h2, prop-driven) / centered global `Search` / user avatar circle showing the first letter of their email (**decorative — not clickable, no dropdown**).
- `src/components/Search.jsx` — debounced 300ms global search hitting `GET /api/search?query=`. Dropdown with icon, title, "Note"/"Task" badge, results count. Clicking a result navigates to `/notes` or `/tasks` but **does not deep-link to the item**.

Login and Register are full-bleed standalone screens that do *not* use the shell.

---

## 4. Screen-by-screen: what exists now

### Login
Centered card on an "obsidian" radial-gradient background with two large blurred color blobs (indigo top-right, green bottom-left). Contains: "Pryvora" wordmark in Manrope Black at 2.75rem, uppercase tagline "Privacy-First Personal Hub", then a glassmorphic card (`backdrop-filter: blur(24px)`) with "Welcome back", email + password fields, a "Forgot password?" link **that is a non-functional no-op**, a full-width indigo gradient glow button, and a link to register. Below the card: two decorative status pills — a green pulsing "System Operational" dot and a lock icon "Your Data Stays Private" (**both fake — not wired to anything**).

**2FA state** swaps the card contents in place: heading, one large centered OTP input (6-digit TOTP, or 8-char recovery code), Verify button, a toggle between authenticator/recovery mode, and "Back to login".

### Register
Same shell as Login. Fields: First Name, Last Name, Email, Password, Confirm Password. Client-side validation (required, passwords match, min 6 chars) surfaced in a red alert box.

### Dashboard
Greeting ("Good morning/afternoon/evening, {first name}"), formatted date, and three stat pills (*n* today / *n* upcoming / *n* notes). Then a 2/3 + 1/3 grid:
- **Left (tasks):** three stacked sections — **Overdue** (red), **Today**, **Upcoming** (grouped by day header across the next 7 days). Each row: checkbox, title, colored priority dot, due date, colored left border. Checkbox toggles done inline.
- **Right:** "Recent Notes" — a 2-col grid of small cards (title + 2-line preview). Clickable but **currently navigates nowhere**.

Single fetch to `GET /api/dashboard/`. **Dead code to remove:** a quick-add-task handler and state exist in the component but are never rendered in the JSX.

### Notes
Header ("Your Notes" / "Capture your thoughts and ideas securely") + "New Note" button. Responsive 1/2/3-col card grid: title, last-updated date, 4-line content preview, colored tag badges, hover-revealed edit/delete. Create/Edit modal: title input, content textarea, `TagSelector`. Delete confirmation modal. Inline success/error toasts that auto-dismiss after 3s. Empty state uses a 📝 emoji.

### Tasks
Header + "New Task" button. **Two rows of filter tabs:** category (All / Today / Overdue, each with icon + count badge) and status (All Status / To Do / In Progress / Done). Then a responsive 2–5 column grid of `TaskItem` cards — fixed `h-48`, colored left border by status, checkbox, title (strikethrough when done), 3-line description clamp, and footer badges for status, priority, and due date.

`TaskForm` (create + edit): underline-style title and description inputs, a status Select with colored icons, a **3-way segmented priority control** (Low/Medium/High), and due-date **quick-select chips** (Today / Tomorrow / In 3 days / In a week / In a month) alongside a full DatePicker dialog.

### Calendar
**A 26-line stub.** One Card that says "Coming soon" / "This feature is under development." No calendar UI exists. See §7 — this is your biggest greenfield.

### Settings
Three stacked cards, no tabs:
1. **Two-Factor Authentication** — enable/disable, plus a **3-step wizard modal** (numbered progress dots): QR code → manual-entry secret in a mono block → 6-digit verification. Then a **recovery-codes modal**: mono grid of codes, a yellow "shown only once" warning, Copy / Download buttons.
2. **Change Password** — current, new, confirm.
3. **Manage Tags** — list of tags (color swatch + name) with edit/delete. Edit modal uses a **native `<input type="color">`**.

---

## 5. What's actually wrong with the current design

Be blunt with yourself about these — they're the reason for the redesign.

1. **Two disconnected design languages in one product.** Login/Register are moody and considered (obsidian gradient, glassmorphism, gradient glow button, Manrope display type, ambient blur blobs). The moment you log in, all of it vanishes — the authenticated app is a flat, generic dark admin panel. It reads as two different products stitched together.

2. **The design token system exists but is bypassed.** `index.css` defines the full shadcn CSS-variable set — but it's the **stock, unmodified grayscale default** (every color is `0 0% x%`, literally zero hue, no brand color anywhere). The pages then ignore those tokens and hardcode raw hex inline. Actual counts from the codebase: `#e5e5e5` ×97, `#1a1a1a` ×61, `#888888` ×50, `#666666` ×30, `#2a2a2a` ×29, `#0f0f0f` ×21, `#0a0a0a` ×17. There is no single source of truth.

3. **There is no accent color — there are four.** `#818cf8` (login), `#bdc2ff` (gradient end), `#8b5cf6` (search focus ring), and `indigo-500`/`indigo-600` utility classes (task form). Plus ad-hoc status colors from the raw Tailwind palette.

4. **Dark mode is a lie in both directions.** A `ThemeProvider` toggles a `.dark` class and persists to localStorage — but **no UI anywhere calls `setTheme`**, so it's permanently locked to dark. Meanwhile the app doesn't actually use the `.dark` CSS variants; it hardcodes dark hex directly. The light-mode tokens are dead code.

5. **No brand assets at all.** `src/assets/` contains only the default Vite `react.svg`. No logo, no mark, no imagery. Branding is 100% the word "Pryvora" in Manrope — and only on the login screen.

6. **No type scale, no elevation system, inconsistent radius.** Font sizes mix Tailwind scale with arbitrary values (`text-[2.75rem]`, `text-[0.6875rem]`). Radius mixes `rounded-md`/`lg`/`xl`/`full` with no rule. Shadows are essentially absent outside the login button.

7. **Icon system is inconsistent.** Lucide everywhere, except Notes and the recovery-codes warning, which use raw emoji (📝, ✏️, 🗑️, ⚠️).

8. **The sidebar nav has no icons** — text-only, which makes it feel unfinished next to everything else.

### Worth keeping

- **The motion layer.** `framer-motion` fade/slide-up entrances are applied uniformly (page → section → row stagger), and a `use_reduced_motion` hook genuinely respects `prefers-reduced-motion`. This is the one consistent system in the app. Keep the discipline; restyle the choreography if you want.
- **The interaction patterns that work:** quick-add capture, due-date quick-select chips, the segmented priority control, inline checkbox toggling, the debounced global search.

---

## 6. Design direction — what I want from you

Come to this with a **real aesthetic point of view** and commit to it. Do not produce a safe, templated dashboard. Some starting provocations (pick one and go deep, or propose better):

- **Encrypted-by-default made visible** — a visual language that expresses ciphertext, keys, redaction, and sealed state without being gimmicky.
- **Instrument panel / terminal-adjacent precision** — monospace accents, tight grids, data density, the feeling of a well-machined tool.
- **Calm archive / private study** — warm, paper-adjacent, quiet, the opposite of SaaS neon. A place your thoughts are *kept*, not *processed*.

**Do not default to "dark mode with a purple accent."** That's what it already is, and it's the problem.

### Deliver a real system, not just screens

1. **A brand palette with actual hue**, defined **once** as CSS custom properties in `src/index.css` under Tailwind v4's `@theme`. One accent. Semantic tokens for surface/elevation levels, borders, text hierarchy, and status (overdue / todo / in-progress / done / low / medium / high priority). Every hardcoded hex in the app gets replaced by a token.
2. **A type scale.** Choose the fonts deliberately (you may replace Inter/Manrope). Define the ramp; no more arbitrary one-off sizes.
3. **Elevation, radius, and border rules.** State them, then apply them consistently.
4. **Light and dark themes that both work** — and **build the theme toggle UI** that's currently missing. The plumbing already exists in `theme-provider.jsx`; it just has no control.
5. **A logo / wordmark**, even a simple typographic mark. Put it in the sidebar, not just on login.
6. **Icons in the sidebar nav.** Normalize all emoji to lucide.
7. **Unify auth and app** into one visual language.

---

## 7. Design the Calendar page from scratch

This is the biggest opportunity: **the backend is fully built and the UI does not exist.** You are not restyling anything — you are designing it.

The API (`/api/event`) supports `GET /` (with an optional `?from&to` date range), `POST /`, `PATCH /{id}`, `DELETE /{id}`. The `CalendarEvent` entity has: `title` (plaintext), `description` (**encrypted**), `location`, `startsAt`, `endsAt`, `allDay` (bool), `reminderAt`.

`react-day-picker` and a shadcn `calendar` primitive are already installed. Design the month/week/day views, the event creation and detail flows, and how events relate visually to tasks with due dates. Make it feel native to the system you've designed — this screen should be the proof that the new design language holds up under real complexity.

---

## 8. Data model (for accurate UI)

| Entity | Fields |
|---|---|
| **User** | firstName *(encrypted)*, lastName *(encrypted)*, email (plaintext, login id), twoFactorEnabled, recoveryCodes |
| **Note** | title (plaintext), content (**encrypted**), tags (M:N), createdAt/updatedAt |
| **Task** | title (plaintext), description (**encrypted**), status (`todo`/`in_progress`/`done`), priority (`low`/`medium`/`high`), dueDate, createdAt/updatedAt |
| **Tag** | name, color (user-chosen hex), M:N with notes |
| **CalendarEvent** | title, description (**encrypted**), location, startsAt, endsAt, allDay, reminderAt |

The tradeoff worth understanding: **structured, filterable metadata stays plaintext; free-text bodies are encrypted.** That's why search only finds titles. Your design should make this honest rather than hide it.

**Note:** tags carry a **user-chosen arbitrary hex color**. Your palette must survive arbitrary user colors sitting next to it — design the tag badge so it stays legible and doesn't fight the system.

### API endpoints
`/api/auth/*` (register, login, verify-2fa, refresh, logout) · `/api/2fa/*` (setup, enable, disable, status, recovery-codes) · `/api/user/me` · `/api/notes` · `/api/task` (+ `/today`, `/overdue`, `/quickAdd`) · `/api/event` · `/api/tag` · `/api/dashboard/` · `/api/search?query=`

All calls go through `api_request()` in `src/contexts/AuthContext.jsx` (attaches the Bearer token, auto-retries once on 401 via refresh). There is no separate API service module — pages call it directly with inline URLs.

---

## 9. Scope and ground rules

**In scope:** all 7 screens, the app shell, every modal and empty/loading/error state, the token system, and the Calendar (new).

**Out of scope:** email triage, document uploads, notifications, AI assistant — roadmap only, not built. Don't design them.

**Rules:**
- Do not break existing functionality. Auth, 2FA, CRUD, and search all work — this is a redesign, not a rewrite of behavior.
- Keep `snake_case` naming and Allman braces to match the codebase.
- Keep `use_reduced_motion` respected in any new motion.
- Every state matters: loading, empty, error, and the "first run, no data at all" case. A new user sees **empty everything** — the empty states are the real first impression, so design them properly rather than dropping in an emoji and a shrug.
- Accessibility is not optional: real focus rings, WCAG AA contrast in both themes, keyboard paths through every modal.

**Start by proposing the design direction and the token system, and show me the Dashboard and Calendar as proof, before touching all 7 screens.**
