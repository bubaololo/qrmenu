# Localization

---

- [Source language & switcher](#source)
- [Manual translation](#manual)
- [AI translation](#ai)
- [Untranslated fallback](#fallback)
- [Changing the source language](#change-source)

<a name="source"></a>
## Source language & switcher

A menu has a **source language** (`source_locale`) — the language the original
texts are entered in. The editor header has a **content language switcher**: it
controls which language you currently edit/view names in.

![Language switcher](/img/docs/en/locale.png)

> {warning} New sections and dishes can only be created in the source language. Translations of existing items are edited in other locales.

<a name="manual"></a>
## Manual translation

Switch the active language to the target one and edit a dish's name/description —
a translation is saved in that locale without touching the source text. On the
guest menu, `?lang=<locale>` shows the translation.

<a name="ai"></a>
## AI translation

Next to an untranslated language there's a **✨ (translate with AI)** button — it
starts a background translation (queued). It's **asynchronous**: translations
appear after a while, refresh the page.

> {info} AI translation is non-deterministic and depends on the LLM provider. For a guaranteed result, edit the translation manually.

<a name="fallback"></a>
## Untranslated fallback

If a field isn't translated into the requested language, the guest menu shows the
**source-language** text. Example: the name is translated to `vi` but the
description isn't → on `?lang=vi` the name is Vietnamese, the description is the
source.

<a name="change-source"></a>
## Changing the source language

In the switcher, a translated locale has a "star" icon — **"Make the original
language"**. Condition: the target language must be **fully translated** (every
source field). After the `source_locale` change, existing translations are kept,
and new items are written in the new language.

> {primary} If the translation is incomplete, the operation is rejected (translate all fields first).
