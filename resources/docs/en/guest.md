# Guest menu

---

- [Page URLs](#urls)
- [How modifiers render](#render)
- [Price preview & the button](#price)
- [Ordering & snapshots](#order)

<a name="urls"></a>
## Page URLs

- **Browse:** `/{restaurant}` (and `/{restaurant}/{lang}`), where the first
  segment is the restaurant's numeric id or uniqid.
- **Order from a table:** `/{restaurant}/t/{table}/{lang?}` — ordering is only
  available on this URL (a table identifier is required).

![Guest menu](/img/docs/en/guest.png)

<a name="render"></a>
## How modifiers render

Tapping a card opens a sheet with the modifiers:

- **`replace` group (Size)** — single-select chips; the "default" option is
  pre-selected.
- **`add` group (Extras)** — multi-select; each option shows `+delta`.

![Dish sheet](/img/docs/en/guest-sheet.png)

<a name="price"></a>
## Price preview & the button

The total is computed live: the base (or the absolute price of the chosen size
for `replace`) **plus** the sum of the selected add-on deltas (honouring
size-dependent pricing).

The "Add" button is **blocked** if the add group's selection rules
(`selection_min`/`selection_max`) aren't met. `replace` groups never block the
button (an option is always pre-selected). For a non-orderable dish
(`is_orderable=false`) the button is hidden.

<a name="order"></a>
## Ordering & snapshots

On the table URL, add items to the cart and place the order.

> {primary} The price is computed by the **server** from the current menu — the client doesn't set it (anti-tamper). An invalid selection (violating min/max/required, or a foreign option) is rejected (422).

> {success} **Order history is immutable.** At order time, snapshots of the name and price are stored. If the dish is later edited or deleted in the admin — the menu updates, but the order line keeps the old name and price.
