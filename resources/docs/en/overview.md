# Admin panel overview

---

- [What this is](#what)
- [Two surfaces: admin & guest menu](#surfaces)
- [The menu management flow](#flow)
- [Quick start](#quick-start)
- [Documentation language](#language)

<a name="what"></a>
## What this is

**QRMenu** is a digital-menu platform for restaurants. A guest scans a QR code
and opens the menu in the browser; staff manage the content through the admin
panel (the "QRMenu Admin" React app). This documentation covers the **full menu
management flow** in the admin and how each change is reflected on the guest page.

<a name="surfaces"></a>
## Two surfaces

> {primary} Every change in the admin is reflected on the **public guest menu**. This guide shows both sides.

- **Admin** — the menu editor: sections, dishes, prices, modifiers, translations.
- **Guest menu** — the server-rendered public page at `/{restaurant}` (and
  `/{restaurant}/t/{table}` for ordering) that a guest sees.

<a name="flow"></a>
## The menu management flow

Managing a menu goes top-down:

1. A **menu** is created for a restaurant (one menu per restaurant).
2. **Sections** (categories) group the dishes.
3. **Dishes** are added to sections: name, description, price, photo, visibility flags.
4. **Modifiers** (sizes, add-ons) are created in a shared library and attached to dishes.
5. **Overrides** change a modifier group's behaviour for a specific dish.
6. **Localization** — translations into other languages.
7. **Guest menu** — preview and accept orders.

Each step is described in the matching section on the left.

<a name="quick-start"></a>
## Quick start

> {info} To open the editor, pick a restaurant and go to the **Menu** section.

1. Open the menu editor — you'll see the **Dishes** and **Modifiers** tabs.
2. Click **Add category**, type a name, save.
3. Expand the category and click **Add item** — fill in the name and price.
4. On the **Modifiers** tab create a group (e.g. "Size") from a preset.
5. In the dish editor attach the group and, if needed, set overrides.
6. Open the preview (the eye icon) — check how the menu looks to a guest.

![Menu editor](/img/docs/en/editor.png)

<a name="language"></a>
## Documentation language

> {success} The version switcher in the top-right acts as a **language switcher**: Русский (`ru`), English (`en`), Tiếng Việt (`vi`).
