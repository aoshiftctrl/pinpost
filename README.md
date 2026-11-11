# PinPost (`shft-pin-post`)

WordPress-Plugin, das einen veröffentlichten Beitrag an die erste Stelle des Kategorie-Archives pinnt. Es kann immer nur ein Beitrag gepinnt sein.

- **Version:** 1.1.2
- **Voraussetzungen:** WordPress ≥ 6.6, PHP ≥ 8.0, Block-Editor

## Funktionen

- Checkbox „In Kategorie anpinnen“ im Status-Bereich des Beitragspanels.
- Höchstens ein gepinnter Beitrag. Ist schon einer gepinnt, ist die Checkbox deaktiviert und nennt dessen Titel.
- Nur veröffentlichte Beiträge können gepinnt werden.
- Wird der gepinnte Beitrag zum Entwurf, privat, geplant, in den Papierkorb verschoben oder gelöscht, wird der Pin entfernt.
- Im Frontend steht der Beitrag in der Haupt-Query der Archive seiner eigenen Kategorien vorn, wegen `include_children` auch in denen der Elternkategorien. Startseite, Suche und Tag-Archive bleiben unverändert.
- CSS-Klasse `is-pinned` am gepinnten Beitrag (über `post_class()`).

## Installation

1. Ordner nach `wp-content/plugins/shft-pin-post` kopieren.
2. Plugin unter „Plugins“ aktivieren.
3. Veröffentlichten Beitrag öffnen und „In Kategorie anpinnen“ anhaken.

## Aufbau

| Datei               | Inhalt                                                    |
| ------------------- | --------------------------------------------------------- |
| `shft-pin-post.php` | Speicherung, REST-Feld, Sortierung, CSS-Klasse, Editor-Assets |
| `editor.js`         | Checkbox im Block-Editor                                  |

Alle PHP-Funktionen liegen im Namespace `Shft\PinPost`.

### Speicherung

Der Pin liegt in der autoloaded Option `shft_pp_pinned_id` (Beitrags-ID, `0` = keiner). Eine einzige Option statt Post-Meta macht mehr als einen Pin unmöglich und spart eine Meta-Query pro Archivaufruf. `pinned_id()` prüft zusätzlich, ob der Beitrag noch existiert und veröffentlicht ist.

### REST-API

Feld `shft_pinned` (boolean) an `/wp/v2/posts`:

- Nur im Kontext `edit` – nicht öffentlich lesbar.
- Schreiben erfordert `edit_post` für den Beitrag.
- Fehler:
  - `shft_pp_taken` (409) – ein anderer Beitrag ist bereits gepinnt.
  - `shft_pp_not_published` (400) – der Beitrag ist nicht veröffentlicht.
- Der `update_callback` eines REST-Felds läuft erst nach `wp_update_post()`. Deshalb prüft `rest_pre_insert_post` die Konflikte vorher und lehnt den ganzen Request ab, bevor etwas gespeichert wird.
- Die Vorabprüfung rechnet mit dem Status, den `wp_insert_post()` tatsächlich speichert: `effective_status()` bildet nach, dass `publish` mit Datum in der Zukunft zu `future` wird und umgekehrt.
- Der Filter läuft mit Priorität `PHP_INT_MAX`, damit Filter anderer Plugins kein `WP_Error` statt des erwarteten `stdClass` bekommen.

```bash
curl -X POST https://example.com/wp-json/wp/v2/posts/123 \
  -u user:application-password \
  -H 'Content-Type: application/json' \
  -d '{"shft_pinned": true}'
```

### Sortierung

`posts_orderby` stellt in der Haupt-Query von Kategorie-Archiven `ID = <pin> DESC` vor die bestehende Sortierung. Der Beitrag wird nicht eingefügt, sondern nur umsortiert. Die Pagination bleibt dadurch korrekt.

### Hervorheben per CSS

```css
.is-pinned {
  border-left: 4px solid currentColor;
}
```

## Upgrade von 1.0

Version 1.0 speicherte den Pin als Post-Meta `_shft_pinned`. Beim ersten Laden von 1.1 wird ein vorhandener Pin in die Option übernommen, danach werden alle `_shft_pinned`-Einträge gelöscht. Code, der `_shft_pinned` direkt liest oder schreibt, muss auf `Shft\PinPost\pinned_id()` bzw. das REST-Feld umgestellt werden.

## Bekannte Einschränkungen

- Jeder mit `edit_post` darf pinnen (bewusst, keine Prüfung auf `edit_others_posts`). Autoren können also ihre eigenen veröffentlichten Beiträge pinnen und blockieren damit den einen Pin der Website, bis sie oder ein Redakteur ihn lösen. Fremde Pins lösen können sie nicht.
- Passwortgeschützte Beiträge können gepinnt werden und bleiben es.
- Der Editor kennt den aktuellen Pin nur vom Laden der Seite. Pinnt jemand anderes in der Zwischenzeit, meldet der Server beim Speichern `shft_pp_taken` und speichert nichts.
- Restfenster: Pinnt jemand zwischen der Vorabprüfung und dem `update_callback` desselben Requests, ist der Beitrag schon gespeichert, wenn der Pin abgelehnt wird. Der Editor meldet dann einen Fehler, obwohl der Inhalt übernommen wurde.
- Der Beitrag steht auch im Archiv der Elternkategorie vorn (Kategorie-Archive enthalten Unterkategorien).
- Die Checkbox bleibt mit „Bereits gepinnt“ gesperrt, wenn der Pin nach dem Laden woanders gelöst wurde. `window.shftPinPost` wird nur beim Seitenaufruf gesetzt, erst ein Neuladen gibt die Checkbox frei.
- `is-pinned` hängt am Beitrag, nicht am Archiv: Die Klasse erscheint überall, wo `post_class()` läuft (Startseite, Suche, Tag-Archive, Einzelansicht, Nebenschleifen). Für das Kategorie-Archiv allein `.category .is-pinned` verwenden. Bei Nachladen über `admin-ajax.php` fehlt die Klasse, weil `is_admin()` dort `true` ist.
- Feeds sind nicht ausgenommen: Im Kategorie-Feed steht der gepinnte Beitrag dauerhaft an erster Stelle und verdrängt den ältesten Eintrag.

## Changelog

### 1.1.2

- `effective_status()`: Die Vorabprüfung erkennt, dass `publish` mit Datum in der Zukunft als `future` gespeichert wird, und lehnt den Pin vor dem Speichern ab.
- `check()` prüft den Status vor dem Vergleich mit dem bestehenden Pin. `{status: 'draft', shft_pinned: true}` für den gepinnten Beitrag wird vorab abgelehnt.
- Editor: Ein ungespeicherter Pin wird per `useEffect` verworfen, sobald der Beitrag nicht mehr veröffentlicht wäre (Status oder `isEditedPostBeingScheduled()`). Ein gespeicherter Pin bleibt stehen.
- Editor: Checkbox nur bei `getCurrentPostType() === 'post'`, nicht beim Bearbeiten von Templates und Patterns.
- `get_callback` fällt bei `_fields` ohne `id` auf `get_the_ID()` zurück.
- `rest_pre_insert_post` mit Priorität `PHP_INT_MAX`.
- `orderby()` und `post_class()` ohne Typdeklarationen, mit Prüfung der Argumente.
- Bekannte Einschränkungen ergänzt: gesperrte Checkbox, Geltungsbereich von `is-pinned`, Feeds.
- Plugin-Header aus der Testkopie übernommen (Beschreibung, Author URI).

### 1.1.1

- Pin-Konflikte werden per `rest_pre_insert_post` vor dem Speichern geprüft, ein abgelehnter Pin verwirft den ganzen Request.
- Editor entfernt den Pin bei Statuswechsel nicht mehr selbst, das erledigt `unpin_on_status` beim Speichern.
- Titel über `plain_title()`: ohne Entities, Texturize und „Geschützt:“-Präfix.
- Inline-Script `window.shftPinPost` mit `JSON_HEX_TAG | JSON_HEX_AMP`, da `plain_title()` `<` wieder dekodiert.
- Nur noch `wp.editor.PluginPostStatusInfo`, Fallback auf `wp.editPost` entfernt.
- `ORDER BY`-Fragment über `$wpdb->prepare()`.
- Lizenz im Plugin-Header.

### 1.1.0

- Pin als Option `shft_pp_pinned_id` statt Post-Meta, mit automatischer Übernahme aus 1.0.
- REST-Feld `shft_pinned` nur im Kontext `edit`.
- Klare Fehlermeldung bei Konflikt.
- Pin wird auch beim endgültigen Löschen entfernt.
- Namespace `Shft\PinPost` statt globaler Funktionen mit `function_exists`.

### 1.0.0

- Erste Version.
