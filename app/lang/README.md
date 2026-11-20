# Languages

`en.php` is the source: every on-screen phrase as `key => English text`.

To add a language, copy `en.php` to `<code>.php` (for example `es.php`, `fr.php`, `it.php`, `ja.php`, `zh-HK.php`),
set `'_name'` to the language's own name ("Español"), and translate the right-hand sides.
Missing keys fall back to English, so a language can ship partly translated. The picker appears as soon as a second file exists.

- `{name}`, `{n}`, `{date}` are filled in by the app. Keep them, and move them where the sentence needs them.
- Counted phrases come in pairs, `key.one` and `key.other`.
- `data.*` are built-in lists (trackers, templates, feelings, achievements). `msg.*` are messages. `js.*` are phrases used by scripts. `title.*` are browser-tab titles.
- Dates: set `date.months`, `date.months_short`, `date.days`, `date.days_short`, and optionally `date.fmt.<english pattern>` to reorder (for example `'date.fmt.M j' => 'j \d\e M'`).
- Japanese and Chinese movie title cards and PDFs need a CJK font in `app/fonts/` (not needed for the screens).
