<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Menu #{{ $restaurant->id }}</title></head>
<body>
<pre>

=== RESTAURANT ===

id:               {{ $restaurant->id }}
name_local:       {{ $restaurant->name_local }}
name_en:          {{ $restaurant->name_en }}
city:             {{ $restaurant->city }}
province:         {{ $restaurant->province }}
country:          {{ $restaurant->country }}
district:         {{ $restaurant->district }}
address_local:    {{ $restaurant->address_local }}
address_en:       {{ $restaurant->address_en }}
phone:            {{ $restaurant->phone }}
phone2:           {{ $restaurant->phone2 }}
currency:         {{ $restaurant->currency }}
primary_language: {{ $restaurant->primary_language }}
opening_hours:    {{ json_encode($restaurant->opening_hours, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}
created_at:       {{ $restaurant->created_at }}
updated_at:       {{ $restaurant->updated_at }}

=== ACTIVE MENU ===

@if (!$menu)
(no active menu)
@else
id:                 {{ $menu->id }}
restaurant_id:      {{ $menu->restaurant_id }}
detected_date:      {{ $menu->detected_date?->toDateString() }}
source_images_count: {{ $menu->source_images_count }}
is_active:          {{ $menu->is_active ? 'true' : 'false' }}
created_from_menu_id: {{ $menu->created_from_menu_id }}
created_at:         {{ $menu->created_at }}
updated_at:         {{ $menu->updated_at }}

=== SECTIONS ({{ $menu->sections->count() }}) ===

@foreach ($menu->sections as $section)
--- section #{{ $section->id }} ---
name_local:  {{ $section->name_local }}
name_en:     {{ $section->name_en }}
sort_order:  {{ $section->sort_order }}

  items ({{ $section->items->count() }}):
@foreach ($section->items as $item)
  --- item #{{ $item->id }} ---
  name_local:          {{ $item->name_local }}
  name_en:             {{ $item->name_en }}
  description_local:   {{ $item->description_local }}
  description_en:      {{ $item->description_en }}
  starred:             {{ $item->starred ? 'true' : 'false' }}
  price_type:          {{ $item->price_type?->value }}
  price_value:         {{ $item->price_value }}
  price_min:           {{ $item->price_min }}
  price_max:           {{ $item->price_max }}
  price_unit:          {{ $item->price_unit }}
  price_unit_en:       {{ $item->price_unit_en }}
  price_original_text: {{ $item->price_original_text }}
  sort_order:          {{ $item->sort_order }}

  variations ({{ $item->variations->count() }}):
@foreach ($item->variations as $variation)
    variation #{{ $variation->id }}: type={{ $variation->type->value }} name_local={{ $variation->name_local }} name_en={{ $variation->name_en }} required={{ $variation->required ? 'true' : 'false' }} allow_multiple={{ $variation->allow_multiple ? 'true' : 'false' }}
@foreach ($variation->options as $opt)
      option #{{ $opt->id }}: name_local={{ $opt->name_local }} name_en={{ $opt->name_en }} price_adjust={{ $opt->price_adjust }} is_default={{ $opt->is_default ? 'true' : 'false' }}
@endforeach
@endforeach

  option_groups ({{ $item->optionGroups->count() }}):
@foreach ($item->optionGroups as $group)
    group #{{ $group->id }}: name_local={{ $group->name_local }} name_en={{ $group->name_en }} min={{ $group->min_select }} max={{ $group->max_select }}
@foreach ($group->options as $opt)
      option #{{ $opt->id }}: name_local={{ $opt->name_local }} name_en={{ $opt->name_en }} price_adjust={{ $opt->price_adjust }}
@endforeach
@endforeach

@endforeach
@endforeach
@endif
</pre>
</body>
</html>
