# Sections & dishes

---

- [Opening the editor](#open)
- [Sections (categories)](#sections)
- [Dishes](#items)
- [Price](#price)
- [Visibility flags](#flags)
- [Photo, clone, delete](#misc)

<a name="open"></a>
## Opening the editor

Pick a restaurant and open the **Menu** section. The editor opens on the
**Dishes** tab; next to it is the **Modifiers** tab. Top-right: the content
language switcher and the preview button (the eye icon).

![Menu editor](/img/docs/en/editor.png)

<a name="sections"></a>
## Sections (categories)

Sections group dishes.

- **Create:** the **Add category** button → type a name → **Save**.
- **Rename:** edit the name in the section row and save.
- **Order:** drag a section by its handle (the "⋮⋮" grip) — the order is
  applied to the guest menu immediately.
- **Hide/show:** the eye button (active flag). A hidden section and all its
  dishes **disappear from the guest page**; re-enable it to bring them back.
- **Delete:** the trash icon → confirm. Deleting cascades to the section's dishes.

> {primary} A deactivated section is not sent to the guest at all (server-side filter), it isn't merely hidden visually.

<a name="items"></a>
## Dishes

Expand a section and click **Add item** — the dish editor opens.

![Dish editor](/img/docs/en/item.png)

Fields:

- **Name** (required) and **Description** — in the active language.
- **Price** — see below.
- **Photo** — upload with a 1:1 crop.
- **Modifiers** — attach groups (see [Modifiers](/{{route}}/{{version}}/modifiers)).
- **Flags** — visibility, orderable, recommended.

> {warning} Saving is blocked while the name in the active language is empty.

<a name="price"></a>
## Price

The dish editor has a single numeric price field — it creates a **fixed** price
(`fixed`). On the guest menu it's shown as an integer with a thousands separator
and the currency symbol (e.g. `60 000 ₫`).

> {info} Range / "from" / variable prices exist in the data model and menu recognition, but are **not created manually** in the dish editor.

<a name="flags"></a>
## Visibility flags

- **Show in menu** (`is_visible`): turn off — the dish **disappears** from the guest.
- **Orderable** (`is_orderable`): turn off — the dish is visible, but the
  **"Add" button in the sheet is hidden** (it can't be ordered).
- **Recommended** (`starred`): marks the dish with a star on the guest menu.

> {info} Turning off "Show in menu" automatically clears "Orderable" and "Recommended".

<a name="misc"></a>
## Photo, clone, delete

- **Photo:** the upload is asynchronous — after the dish is saved it appears on
  the guest menu (with a cache refresh).
- **Clone:** the copy icon on a dish row → confirm. A copy is created with the
  same attributes, translations and **attached modifier groups**.
- **Delete:** from the dish editor (the trash icon in the header) → confirm.
- **Reorder dishes:** drag within the section; the order applies to the guest menu.
