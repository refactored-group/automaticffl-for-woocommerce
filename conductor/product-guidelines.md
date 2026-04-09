# Product Guidelines: Automatic FFL for WooCommerce

## Brand Voice & Tone

### Tone: Professional and Authoritative
All user-facing text should convey clarity, trustworthiness, and regulatory seriousness. The plugin deals with federal and state firearms compliance — the language must reflect that responsibility.

**Principles:**
- **Be direct and clear** — Use plain language that leaves no ambiguity about what the customer or merchant needs to do.
- **Be informative** — Provide enough context so users understand *why* an action is required, not just *what* to do.
- **Be reassuring** — Compliance can feel intimidating; guide users confidently without creating unnecessary anxiety.
- **Avoid jargon** — Use "FFL dealer" when necessary but explain it on first encounter. Avoid internal acronyms in customer-facing text.

**Examples:**
- Checkout prompt: "Federal law requires firearms to be shipped to a licensed FFL dealer. Please select a dealer below."
- Ammo restriction: "Ammunition cannot be shipped to your selected state. Please review your order."
- Success confirmation: "Your order will be shipped to [Dealer Name] at [Address]."
- Error notice: "Please select an FFL dealer before completing your order."

**Avoid:**
- Casual or playful language ("Oops!", "No worries!")
- Overly technical or legal language that intimidates ("Pursuant to 18 U.S.C. § 922...")
- Vague or passive phrasing ("There might be an issue with your order")

## Visual Identity

### Theme Integration
The plugin's UI elements should blend seamlessly with the merchant's WooCommerce theme. As a plugin operating within diverse storefronts, native appearance is critical.

**Principles:**
- Inherit the theme's typography, button styles, and form element styling wherever possible.
- Use the AutomaticFFL brand (logo, purple/grey palette, Mulish font) sparingly — primarily in the dealer map modal and admin settings, not in general checkout flow.
- Reserve custom styling for compliance-critical elements that need to stand out regardless of theme.

### Color Usage
- **Brand purple** — Used in the dealer map modal, admin branding, and preferred dealer markers.
- **Red/warning tones** — Reserved exclusively for compliance blockers (ammo restrictions, missing dealer selection).
- **Green/success tones** — Used for confirmed dealer selection and successful validation states.
- **Grey** — Secondary elements, non-preferred dealer markers, disabled states.

### Iconography
- Use inline SVG icons rather than icon font libraries (ensures compatibility with themes like Divi that conflict with FontAwesome).
- Keep icons simple and functional — they should clarify, not decorate.

## UX Principles

### 1. Compliance First, Friction Minimal
Every UX decision should prioritize regulatory compliance while minimizing checkout friction. If a compliance step is required, make it as smooth as possible.

### 2. Progressive Disclosure
- Show compliance UI only when relevant (e.g., FFL map only when cart contains firearms).
- Don't burden customers buying non-regulated products with any compliance-related UI.
- Use the cart analyzer to determine what to show and when.

### 3. Clear State Communication
- Always show the customer their current compliance status (dealer selected, restriction detected, etc.).
- Provide actionable next steps when something blocks checkout.
- Use visual indicators (color, icons) alongside text for accessibility.

### 4. Graceful Degradation
- If the Google Maps API is unavailable, provide a fallback search experience.
- If the AutomaticFFL API is unreachable, display a clear error rather than a broken UI.
- Never let a plugin error break the entire WooCommerce checkout.

### 5. Admin Experience
- Settings should be minimal and self-explanatory — store hash, API key, sandbox toggle.
- Provide inline help text for every admin setting.
- Use WooCommerce's native admin UI patterns (settings tabs, notices) for consistency.

## Content Guidelines

### Error Messages
- Start with what happened, then what to do: "Ammunition cannot be shipped to [State]. Please remove ammo items or change your shipping state."
- Never blame the customer.
- Provide a clear resolution path.

### Labels and Placeholders
- Use sentence case for labels ("Select your FFL dealer", not "Select Your FFL Dealer").
- Placeholders should show example input, not instructions.

### Order Notes and Comments
- Include relevant compliance details (dealer name, license number, address).
- Make links clickable for merchant convenience.
- Keep notes factual and concise.

## Accessibility
- All interactive elements must be keyboard navigable.
- Use sufficient color contrast ratios (WCAG AA minimum).
- Provide text alternatives for all visual indicators — never rely on color alone to convey compliance status.
- Screen reader support for the dealer selection flow.
