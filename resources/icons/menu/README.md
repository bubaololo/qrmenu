# Menu icons

Stroke-style 24×24 SVGs from the Hugeicons Rounded / Stroke pack (Food Drinks + Kitchen).
Each filename (without `.svg`) is the icon ID used in:

- `menu_sections.category_icon` — per-section icon
- `config/food_icons.php` — `allowed` whitelist
- `App\Support\FoodIcons::sprite()` — inline sprite generator
- Blade `<use href="#<id>">`

All ink is normalized to `currentColor` at render time.

---

## Meals & proteins

`steak`, `chicken-thighs`, `hamburger-01`, `hamburger-02`, `hotdog`, `sausage`,
`bbq-grill`, `fry`, `noodles`, `rice-bowl-01`, `rice-bowl-02`, `spaghetti`,
`pizza-01`, `pizza-02`, `taco-01`, `taco-02`,
`french-fries-01`, `french-fries-02`,
`sushi-01`, `sushi-02`, `sushi-03`,
`dim-sum-01`, `dim-sum-02`, `mochi`, `popcorn`

## Seafood

`fish-food`, `crab`, `prawn`, `shellfish`, `octopus`, `snail`

## Breads & pastries

`bread-01`, `bread-02`, `bread-03`, `bread-04`,
`croissant`, `pie`, `apple-pie`, `biscuit`, `cinnamon-roll`

## Desserts

`birthday-cake`, `cheese-cake-01`, `cheese-cake-02`,
`cupcake-01`, `cupcake-02`, `cupcake-03`,
`doughnut`, `cookie`,
`ice-cream-01`, `ice-cream-02`, `ice-cream-03`, `ice-cream-04`,
`chocolate`, `lollipop`, `cotton-candy`

## Drinks — hot

`coffee-01`, `coffee-02`, `coffee-beans`, `tea`, `tea-pod`

## Drinks — cold

`bubble-tea-01`, `bubble-tea-02`,
`soft-drink-01`, `soft-drink-02`, `soda-can`, `drink`,
`milk-bottle`, `milk-carton`, `milk-coconut`, `milk-oat`, `yogurt`

## Basics & ingredients

`eggs`, `cheese`, `mushroom`, `broccoli`, `carrot`, `corn`, `pumpkin`,
`avocado`, `vegetarian-food`, `organic-food`, `natural-food`,
`honey-01`, `honey-02`, `nut`

## Fruits

`apple`, `apricot`, `banana`, `cherry`, `grapes`, `orange`, `watermelon`

## Kitchen & service

`chef`, `chef-hat`, `cook-book`, `apron`, `glove`, `matches`,
`dish-01`, `dish-02`, `dish-washer`, `plate`, `fork`, `spoon`,
`knife-01`, `knife-02`, `knife-bread`, `knives`, `kitchen-utensils`,
`pot-01`, `pot-02`, `pan-01`, `pan-02`, `pan-03`,
`pizza-cutter`, `rolling-pin`, `spatula`, `whisk`, `beater`, `hand-beater`,
`mixer`, `blender`, `kettle`, `kettle-1`, `jar`,
`gas-stove`, `oven`, `microwave`, `refrigerator`, `weight-scale`

---

## Suggested mappings

| Section / category                       | Icon               |
|------------------------------------------|--------------------|
| Soups, stews, hot pot                    | `pot-01`           |
| Rice bowls, donburi, curry-rice          | `rice-bowl-01`     |
| Noodle soups, ramen, phở                 | `noodles`          |
| Pasta, spaghetti                         | `spaghetti`        |
| Burgers, sandwich                        | `hamburger-01`     |
| Wraps, shawarma, souvlaki pita           | `taco-01`          |
| Grilled meats, BBQ, kebabs               | `bbq-grill`        |
| Breakfast, eggs, omelet                  | `eggs`             |
| Salads, veggie platters                  | `vegetarian-food`  |
| Seafood, fish                            | `fish-food`        |
| Sushi, nigiri, maki                      | `sushi-01`         |
| Pizza                                    | `pizza-01`         |
| Desserts, pastries                       | `cupcake-01`       |
| Ice cream                                | `ice-cream-01`     |
| Coffee                                   | `coffee-01`        |
| Tea                                      | `tea`              |
| Smoothies, juices, soft drinks           | `drink`            |
| Bubble tea                               | `bubble-tea-01`    |
| Kids menu / specialties                  | `chef-hat`         |
| Sides / extras                           | `plate`            |
| Appetizers / starters                    | `dish-01`          |
