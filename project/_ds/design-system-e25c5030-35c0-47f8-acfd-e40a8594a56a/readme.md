# آرتا فارما — Design System
### گروه داروسازی آرتا فارما · سامانه یکپارچه اتوماسیون اداری

A right-to-left (RTL), Persian-first design system for **Arta Pharma Pharmaceutical Group** (گروه داروسازی آرتا فارما — «آرتا قلب بهسان طب») and its **integrated office-automation platform** (سامانه یکپارچه اتوماسیون اداری): the cartable/inbox, letter workflows, dashboards and admin surfaces staff use every day.

The visual language is taken directly from the brand: a **blue gradient** wash (the login backdrop), the logo's **gold** heart-outline and **teal** fill as accents, generous Persian typography, soft blue-tinted elevation, and frosted-glass surfaces over the gradient.

---

## Sources provided
- `uploads/Screenshot 2026-07-04 112353.png` — the automation-system login screen (blue gradient, glass form, white wordmark). Colors were sampled directly from it.
- `uploads/logo-3-12.png` — the full white lockup (wordmark + heart-ECG mark). Copied to `assets/logo-full-white.png`; the mark was cropped to `assets/logo-mark.png`.
- `IRAN Yekan Pro V3.0 Latest.zip` — **did not arrive** in the upload. See "Fonts / substitutions" below.

No codebase or Figma file was provided; components are an original, standard set tuned to the automation product (see "Intentional additions").

---

## Fonts / substitutions ⚠️
- **Typeface:** the requested licensed **IRANYekan** could not be bundled (its zip failed to upload). The system ships **Vazirmatn** (Google Fonts) — the open-source twin of IRANYekan with near-identical letterforms — via `tokens/fonts.css`. Every rule references `var(--font-fa)`; to swap in the real IRANYekan, add its `@font-face` in `tokens/fonts.css` and change the one `--font-fa` line in `tokens/typography.css`. **Please re-upload the IRANYekan zip so the licensed font can replace the stand-in.**
- **Latin/numeric companion:** IBM Plex Sans / Plex Mono (Google Fonts).
- **Icons:** Lucide (MIT) stroke icons, inlined as SVG in `ui_kits/automation/icons.jsx` — a substitute for the product's own glyphs (none were provided).

---

## CONTENT FUNDAMENTALS — how copy is written
- **Language:** Persian (Farsi), RTL. Formal administrative register — the tone of اتوماسیون اداری.
- **Address:** polite/formal, plural-you. Standard letter openers/closers appear verbatim: «با سلام و احترام»، «خواهشمند است … اقدام فرمایید». System messages use the impersonal passive («درخواست ثبت شد»).
- **Voice:** concise, procedural, respectful. Buttons are verb-first and short: «ثبت درخواست»، «ارسال نامه»، «تأیید و ارجاع»، «انصراف».
- **Casing:** N/A for Persian; Latin labels/version tags stay lowercase.
- **Numerals:** Persian digits (۰۱۲۳۴۵۶۷۸۹) in content and counts; Latin tabular figures (Plex) only inside dense data tables (`.num` / `[data-latin]`).
- **Emoji:** essentially none. A single 👋 appears once on the dashboard greeting; keep emoji out of official letters, tables and status.
- **Domain vocabulary:** کارتابل (cartable/inbox), نامه (letter), وارده/صادره (incoming/outgoing), گردش کار (workflow), ارجاع (referral), پیش‌نویس (draft), دبیرخانه (registry).

---

## VISUAL FOUNDATIONS
- **Color:** primary **brand blue** as a gradient scale (`#2340A4 → #3467DE → #64B0F1`); accents **gold** `#EBB011` (logo outline) and **teal** `#00AEC3` (logo fill). Cool-tinted neutrals (`--ink-*`). Semantic success/warning/danger/info. See `tokens/colors.css`.
- **Type:** Vazirmatn 300–800. Display/H1 in 800/700; body 14.5px with **generous Persian leading** (line-height 1.7). Never use positive letter-spacing on Persian. See `tokens/typography.css`.
- **Spacing:** 4px base grid (`--sp-*`); 268px sidebar; 1280px content max.
- **Backgrounds:** the signature **blue gradient** on auth/marketing surfaces, often with soft radial light blobs and a **frosted-glass** panel (`--glass-*`, `backdrop-filter`). App surfaces are calm — light `--bg-app` (#F1F4F9) canvas with white cards.
- **Elevation:** blue-tinted, layered, soft shadows (`--shadow-sm…xl`); brand/gold/teal **glow** shadows for primary CTAs and focal cards. Inset shadow on inputs/wells.
- **Corners:** rounded and friendly — controls 12px, cards 16px, hero panels 22–28px, pills 999px.
- **Borders/cards:** 1px `--border-subtle` hairline + `--shadow-sm`; optional 4px gradient **accent stripe** on a card's top edge (brand/gold/teal). No colored-left-border cards.
- **Animation:** calm and purposeful. `--dur-fast 120ms` for controls, `--dur-base/slow` for surfaces; standard glide easing, decel-out for entrances, a gentle spring reserved for focal entrances. No infinite/decorative loops. Honors `prefers-reduced-motion`.
- **Hover:** buttons lift shadow slightly; ghost controls fill with `--ink-100`; table rows tint `--blue-50`; cards (when `hoverable`) rise 2px.
- **Press:** buttons translate down 1px + scale 0.99 (subtle shrink).
- **Focus:** 3px `--focus-ring` (blue) halo + brand border on inputs.
- **Imagery vibe:** cool, clinical, optimistic — blues and teal, bright and clean (fits a pharma brand).

---

## ICONOGRAPHY
- **Set:** Lucide (MIT), 2px stroke, round caps/joins — a clean, medical-adjacent line style that reads well at small sizes in a dense RTL admin UI. Substituted because the product ships no icon assets.
- **Delivery:** inlined as SVG paths in `ui_kits/automation/icons.jsx` (`window.AfIcons`). Copy that file into new work, or link Lucide from CDN. Components accept icon **nodes** (they don't hard-depend on any icon library).
- **Color:** icons inherit `currentColor`; tint via the parent's text color or a StatCard/IconButton tone chip.
- **Emoji / unicode as icons:** avoid (one decorative 👋 aside). Trend arrows (↑↓→) in StatCard deltas are the only unicode-as-glyph usage.
- **Logo:** never redraw. Use `assets/logo-mark.png` (works on any background) or `assets/logo-full-white.png` (**white — dark/blue surfaces only**). On light surfaces, pair the mark with the brand name set in type.

---

## Components
Reusable React primitives (`window.DesignSystem_e25c50.*`), grouped by concern under `components/`:

- **forms/** — `Button`, `IconButton`, `Input`, `Select`, `Checkbox`, `Switch`, `Textarea`
- **display/** — `Card`, `StatCard`, `Badge`, `Tag`, `Avatar`
- **feedback/** — `Alert`, `ProgressBar`, `WorkflowStepper`
- **data/** — `Table`
- **navigation/** — `SidebarItem`, `SidebarNav`, `Tabs`

`SidebarItem` is the atomic nav row (used inside the UI-kit `Shell`); `SidebarNav` is the full composed rail (brand header + grouped items + footer) for quick assembly. `WorkflowStepper` tracks multi-stage approval flows (گردش کار).

Each directory has one `@dsCard` showcase HTML; each component has a `.d.ts` (props contract) and a `.prompt.md` (usage). All styling is via CSS custom properties — no CSS-in-JS, no npm deps.

### Intentional additions
No source defined a component inventory, so a standard admin/automation set was authored from the brand guidelines. `SidebarItem`, `StatCard`, and `Table` are included because the automation product is navigation- and data-heavy; `Switch` vs `Checkbox` are split by immediacy (settings vs form submit).

---

## Index / manifest
- `styles.css` — **the entry point** consumers link. `@import`s only.
- `tokens/` — `fonts.css`, `colors.css`, `typography.css`, `spacing.css`, `radii-shadows.css`, `motion.css`.
- `components/<group>/` — the primitives above (`.jsx` + `.d.ts` + `.prompt.md` + one card HTML).
- `guidelines/` — foundation specimen cards (Colors, Type, Spacing, Brand) shown in the Design System tab.
- `ui_kits/automation/` — the office-automation product recreation: `index.html` (interactive: login → dashboard → cartable → compose), `LoginScreen.jsx`, `Shell.jsx`, `DashboardScreen.jsx`, `CartableScreen.jsx`, `ComposeScreen.jsx`, `icons.jsx`, `README.md`.
- `assets/` — `logo-mark.png`, `logo-full-white.png`.
- `SKILL.md` — Agent-Skill wrapper.
- Generated automatically (do not edit): `_ds_bundle.js`, `_ds_manifest.json`, `_adherence.oxlintrc.json`.

Namespace for card/kit HTML: `window.DesignSystem_e25c50`.
