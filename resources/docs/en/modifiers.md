# Modifiers

---

- [Model: library + attaching](#model)
- [Presets](#presets)
- [Creating a group](#create)
- [Options and "default"](#options)
- [Shared editing](#shared)
- [Size-dependent pricing (Advanced)](#size-pricing)
- [Per-dish overrides](#overrides)

<a name="model"></a>
## Model: library + attaching

Modifiers (sizes, add-ons) are a **shared library of groups** that are
**attached** to dishes:

- A **group** has a pricing mode and selection rules: `pricing_mode`
  (`replace` — the option price replaces the dish price; `add` — a surcharge),
  a min/max selection and a "required" flag.
- An **option** inside a group carries a price: for `replace` it's the
  **absolute** price of that choice, for `add` it's a **delta** (surcharge).
- A group is created once and **attached to multiple dishes**; editing the group
  changes it on every attached dish at once.

![Modifiers tab](/img/docs/en/modifiers.png)

> {primary} Editing the group itself (option names, prices) lives ONLY on the Modifiers tab. In the dish editor you only attach groups and set overrides.

<a name="presets"></a>
## Presets

A preset sets `pricing_mode` + min/max + "required" consistently:

Preset | Mode | Selection | Behaviour
------ | ---- | --------- | ---------
**Pick one · price replaces** | `replace` | single | min 1, max 1, required — "Size"
**Pick one · surcharge (+)** | `add` | single | min 1, max 1, required
**Any amount · surcharge (+)** | `add` | multi | min 0, no max — "Extras"
**Up to N · surcharge (+)** | `add` | multi | min 0, max N

<a name="create"></a>
## Creating a group

On the **Modifiers** tab → **Add group**:

1. Type the group name (e.g. "Size").
2. Pick a preset.
3. Fill the options: name and price. For `add` a "+" is shown next to the price.
4. **Save**.

![Modifier group](/img/docs/en/modifier-group.png)

The **"in N dishes"** counter in the list shows how many dishes use the group.
Deleting a group that is in use asks for confirmation and detaches it from those
dishes.

<a name="options"></a>
## Options and "default"

- **Add option** — adds an option; the last one can't be removed.
- **"Default"** (only for `replace` + single): marks the pre-selected option. On
  the guest menu this option is selected on open — otherwise price and validation
  would be off.

<a name="shared"></a>
## Shared editing

A group is shared. Changing an option's price in the library changes it **on
every dish** the group is attached to. This is verifiable on the guest menu:
editing the "S" price is reflected in the size chip of each such dish.

<a name="size-pricing"></a>
## Size-dependent pricing (Advanced)

Sometimes an add-on costs differently depending on the size. This is configured
in the **"Advanced"** block of an `add` group:

1. Open the `add` group (e.g. "Extras") and expand **"Advanced"**.
2. Enable **"Price depends on another group"** and pick the **driver group**
   (a single-select one, usually "Size").
3. Fill the **grid**: the price of each option for each size.

![Size-dependent pricing](/img/docs/en/size-pricing.png)

On the guest menu, picking a size changes the add-on delta and the total per the
grid.

> {info} An empty grid cell = the option's base price; if no driver size is chosen, it also falls back to the base price.

<a name="overrides"></a>
## Per-dish overrides

A group is shared, but its rules can be **overridden for a specific dish** — in
the dish editor, the **Modifiers** section:

- **Attach/detach** a group (toggle). Attaching doesn't change the shared group —
  other dishes are not affected.
- **Required**, **Min**, **Max** — overrides for this dish only.

![Attaching & overrides](/img/docs/en/item-modifiers.png)

> {success} **Important (required ↔ minimum):** on the guest menu `selection_min` is authoritative. Unchecking "Required" for a group on a dish sets the minimum to 0 — and the "Add" button stops being blocked. Re-checking it blocks again until a choice is made.

A min/max override applies to this dish only; another dish with the same group
uses the group's own values.
