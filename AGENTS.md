# AGENTS.md

## Overview
Single-page Tunisian e-commerce landing page for leather moccasins. Arabic RTL, vanilla HTML + Tailwind CSS (CDN) + vanilla JS. No build step, no backend.

## Key gotchas

- **Image paths are broken** — `<img>` tags point to `/mnt/user-data/uploads/Untitled_design.png` paths that don't exist locally. Two local PNGs (`mocassin-marron.png`, `mocassin-noir.png`) are present but unused. Fix `src` attributes to use relative paths like `./mocassin-noir.png`.
- **CDN-only** — Tailwind CSS and Google Fonts loaded from CDN; page needs internet to render correctly.
- **Order form is stub** — `submitOrder()` only `console.log`s; no POST endpoint exists.
- **Countdown timer** — hardcoded to 14:59, resets on page load; no server-side enforcement.

## Verification
Open `mba-landing-page.html` directly in a browser. No server required (aside from CDN access).
