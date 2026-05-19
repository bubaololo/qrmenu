# Menu icons

Stroke-style 24×24 SVGs from the Hugeicons Rounded / Stroke pack (Food Drinks subset).
Each filename (without `.svg`) is the icon ID used in:

- `icons.name` — DB row referenced by `menu_sections.icon_id`
- `config/food_icons.php` — `allowed` whitelist enforced in `SaveMenuAnalysisAction::validateIconName()`
- `App\Support\FoodIcons::sprite()` — inline sprite generator
- Blade `<use href="#<id>">`

All ink is normalized to `currentColor` at render time.

The whitelist is curated for SEA (Vietnam, Thailand) + European cuisine commonly served
in those markets. Names are 1–4 words and semantically explicit so the menu-analyzer
LLM can map a section heading to an icon with minimal ambiguity. Other SVGs in this
folder are kept for reference but are NOT in the whitelist; the LLM may not select them
and they will not render via the FoodIcons sprite during normal flow.

## Whitelisted icons (46)

### Asian mains
`noodle-bowl`, `rice-bowl`, `dim-sum`, `sushi`, `mochi`

### Western mains
`pizza`, `spaghetti`, `burger`, `hotdog`, `wrap`, `french-fries`

### Grill & meat
`steak`, `chicken-leg`, `sausage`, `grill`

### Seafood
`fish`, `prawn`, `crab`, `shellfish`, `octopus`, `snail`

### Soup, curry, hot pot
`soup-pot`

### Bread & pastry
`baguette`, `croissant`, `pie`

### Breakfast & dairy
`eggs`, `cheese`

### Vegetarian / healthy
`salad`, `healthy-food`

### Sweets
`cupcake`, `cake`, `donut`, `cookie`, `ice-cream`, `chocolate`

### Hot drinks
`iced-coffee`, `hot-coffee`, `tea`

### Cold drinks
`bubble-tea`, `soft-drink`, `soda-can`, `cocktail`, `milk`, `yogurt`

### Chef's specials
`chef-hat`

### Add-ons / extras
`extras`

---

## Section → icon cues

| Section heading                                          | Icon              |
|----------------------------------------------------------|-------------------|
| Phở, Bún, Hủ tiếu, ramen, pad thai, mì xào, noodle soups | `noodle-bowl`     |
| Cơm tấm, fried rice, donburi, khao pad, curry rice       | `rice-bowl`       |
| Dim sum, bao, dumplings, gyoza                           | `dim-sum`         |
| Sushi, nigiri, maki, sashimi                             | `sushi`           |
| Mochi, daifuku                                           | `mochi`           |
| Pizza                                                    | `pizza`           |
| Pasta, spaghetti, lasagna                                | `spaghetti`       |
| Burgers, sandwich (Western)                              | `burger`          |
| Hotdogs                                                  | `hotdog`          |
| Wraps, tacos, shawarma, burrito                          | `wrap`            |
| Fries, sides                                             | `french-fries`    |
| Steaks, beef                                             | `steak`           |
| Chicken, poultry                                         | `chicken-leg`     |
| Sausages                                                 | `sausage`         |
| BBQ, grill, satay, kebabs, bún chả                       | `grill`           |
| Fish                                                     | `fish`            |
| Prawn, shrimp, tôm                                       | `prawn`           |
| Crab, cua                                                | `crab`            |
| Clams, mussels, oysters                                  | `shellfish`       |
| Octopus, squid, mực                                      | `octopus`         |
| Escargot, snail, ốc                                      | `snail`           |
| Soups, stews, hot pot, lẩu, tom yum, curry               | `soup-pot`        |
| Bánh mì, baguette sandwiches                             | `baguette`        |
| Croissant, pastries                                      | `croissant`       |
| Pies (savory or sweet)                                   | `pie`             |
| Breakfast, eggs, omelet                                  | `eggs`            |
| Cheese plate                                             | `cheese`          |
| Salads, gỏi, raw veg                                     | `salad`           |
| Healthy, organic, vegan                                  | `healthy-food`    |
| Cupcakes, sweet pastries                                 | `cupcake`         |
| Cake (slice or whole)                                    | `cake`            |
| Donuts                                                   | `donut`           |
| Cookies, biscuits                                        | `cookie`          |
| Ice cream, gelato                                        | `ice-cream`       |
| Chocolate, sweets                                        | `chocolate`       |
| Iced coffee, cà phê đá, cold brew                        | `iced-coffee`     |
| Hot coffee, espresso, cappuccino                         | `hot-coffee`      |
| Tea (any)                                                | `tea`             |
| Bubble tea, milk tea                                     | `bubble-tea`      |
| Smoothies, juices, sinh tố, coconut water, fresh drinks  | `soft-drink`      |
| Canned drinks                                            | `soda-can`        |
| Cocktails, wine, beer                                    | `cocktail`        |
| Plain milk drinks                                        | `milk`            |
| Yogurt, lassi, sữa chua                                  | `yogurt`          |
| Chef's specials, signature, recommended                  | `chef-hat`        |
| Add-ons, extras, toppings, "thêm" sections               | `extras`          |
