=== PinPost ===
Contributors: aoshiftctrl
Tags: pin, sticky, kategorie, archiv
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.2
License: CC BY-ND 4.0
License URI: https://creativecommons.org/licenses/by-nd/4.0/

Pinnt einen veröffentlichten Beitrag an die erste Stelle des Kategorie-Archives. Es kann immer nur ein Beitrag gepinnt sein.

== Description ==

Im Block-Editor erscheint im Status-Bereich des Beitragspanels die Checkbox „In Kategorie anpinnen“. Ein gepinnter Beitrag steht in jedem Archiv seiner eigenen Kategorien an erster Stelle.

* Es gibt immer höchstens einen gepinnten Beitrag.
* Nur veröffentlichte Beiträge können gepinnt werden.
* Ist schon ein anderer Beitrag gepinnt, ist die Checkbox deaktiviert und nennt den Titel des gepinnten Beitrags.
* Wird der gepinnte Beitrag zum Entwurf, privat, geplant, in den Papierkorb verschoben oder gelöscht, wird der Pin automatisch entfernt.
* Die Sortierung erfolgt per ORDER BY in der Haupt-Query. Die Pagination bleibt korrekt, der Beitrag erscheint nur in seinen eigenen Kategorien und deren Elternkategorien.
* Der gepinnte Beitrag bekommt im Frontend die CSS-Klasse `is-pinned`.

= Technik =

* Option `shft_pp_pinned_id` – ID des gepinnten Beitrags, `0` = keiner.
* REST-Feld `shft_pinned` (boolean) an `/wp/v2/posts`, nur im Kontext `edit` sichtbar. Schreiben setzt oder löst den Pin. Konflikte liefern einen Fehler mit klarer Meldung (`shft_pp_taken`, HTTP 409, bzw. `shft_pp_not_published`, HTTP 400). Die Prüfung läuft vor dem Speichern, ein abgelehnter Pin verwirft also die ganze Änderung.

== Installation ==

1. Den Ordner `shft-pin-post` nach `wp-content/plugins/` kopieren. Bei einem GitHub-ZIP heißt der entpackte Ordner `pinpost-main` und muss vorher in `shft-pin-post` umbenannt werden, sonst passt er nicht zur Text Domain.
2. Das Plugin unter „Plugins“ aktivieren.
3. Einen veröffentlichten Beitrag im Block-Editor öffnen und „In Kategorie anpinnen“ anhaken.

== Frequently Asked Questions ==

= Wie hebe ich den gepinnten Beitrag optisch hervor? =

Über die Klasse `is-pinned`, z. B.:

`.is-pinned { border-left: 4px solid currentColor; }`

Voraussetzung ist, dass das Theme `post_class()` verwendet.

Die Klasse hängt am Beitrag, nicht am Archiv. Sie erscheint überall, wo das Theme `post_class()` aufruft, also auch auf der Startseite, in der Suche, in Tag-Archiven und in der Einzelansicht. Soll die Hervorhebung nur im Kategorie-Archiv gelten:

`.category .is-pinned { border-left: 4px solid currentColor; }`

Bei Beiträgen, die per `admin-ajax.php` nachgeladen werden, fehlt die Klasse.

= Wo erscheint der gepinnte Beitrag? =

Nur in der Haupt-Query von Kategorie-Archiven, und dort nur in den Kategorien, denen der Beitrag zugeordnet ist. Da Kategorie-Archive in WordPress auch die Beiträge ihrer Unterkategorien zeigen, steht der Beitrag auch im Archiv der Elternkategorie vorn. Startseite, Suche und Tag-Archive bleiben unverändert.

Das gilt auch für die Feeds der Kategorien (`/category/…/feed/`): Dort steht der gepinnte Beitrag dauerhaft an erster Stelle, unabhängig von seinem Datum.

= Warum schlägt das Speichern mit „Bereits gepinnt“ fehl? =

Der Editor kennt den aktuellen Pin nur vom Laden der Seite. Hat in der Zwischenzeit jemand einen anderen Beitrag gepinnt, lehnt der Server das Speichern ab, und keine der Änderungen wird übernommen. Haken entfernen und erneut speichern. In einem sehr kurzen Zeitfenster, wenn zwei Personen fast gleichzeitig speichern, kann der Beitrag trotzdem gespeichert werden, während nur der Pin abgelehnt wird.

= Warum bleibt die Checkbox gesperrt, obwohl der Pin gelöst wurde? =

Aus demselben Grund: Der Editor kennt den aktuellen Pin nur vom Laden der Seite. Wurde der Pin inzwischen in einem anderen Tab oder von jemand anderem gelöst, bleibt die Checkbox mit „Bereits gepinnt“ gesperrt, bis der Editor neu geladen wird.

= Wer darf pinnen? =

Jeder, der den Beitrag bearbeiten darf (`edit_post`). Autoren können also ihre eigenen veröffentlichten Beiträge pinnen.

Das ist bewusst so. Der Pin gilt für die ganze Website und es gibt nur einen: Pinnt ein Autor seinen Beitrag, sehen alle anderen „Bereits gepinnt“, bis er den Pin löst oder ein Redakteur ihn löst (Redakteure dürfen jeden Beitrag bearbeiten). Fremde Pins kann ein Autor nicht lösen, nur den Pin des eigenen Beitrags. Wer das nicht möchte, sollte Autoren keine Beiträge veröffentlichen lassen.

== Changelog ==

= 1.1.2 =
* Ein Pin zusammen mit einem Datum in der Zukunft wird jetzt vor dem Speichern abgelehnt. Bisher wurde der Beitrag geplant und verschwand von der Website, obwohl der Editor einen Fehler meldete.
* Wird der gepinnte Beitrag im selben Request zum Entwurf und erneut gepinnt, wird das ebenfalls vor dem Speichern abgelehnt.
* Im Editor wird ein noch nicht gespeicherter Pin entfernt, sobald der Beitrag auf Entwurf, privat, ausstehend oder ein Datum in der Zukunft gestellt wird. Bisher scheiterte daran das ganze Speichern.
* Die Checkbox erscheint nicht mehr, wenn im Beitragseditor ein Template oder ein synchronisiertes Pattern bearbeitet wird.
* `shft_pinned` stimmt jetzt auch, wenn die Antwort per `_fields` ohne `id` angefordert wird.
* Die Vorabprüfung läuft nach den Filtern anderer Plugins, die Frontend-Filter vertragen abweichende Werte anderer Plugins und Themes.
* Dokumentiert: `is-pinned` gilt überall im Frontend, der Pin wirkt auch in Kategorie-Feeds, die Checkbox bleibt bis zum Neuladen gesperrt.
* Beschreibung im Plugin-Header korrigiert, Author URI ergänzt.

= 1.1.1 =
* Ein abgelehnter Pin (bereits gepinnt, nicht veröffentlicht) verhindert jetzt das ganze Speichern. Bisher wurde der Beitrag trotz Fehlermeldung gespeichert.
* Der Pin geht nicht mehr verloren, wenn im Editor der Status kurz geändert und wieder auf „Veröffentlicht“ gestellt wird. Der Pin wird erst beim Speichern serverseitig entfernt.
* Titel in Editor und Fehlermeldung ohne HTML-Entities und ohne „Geschützt:“-Präfix.
* Der Titel des gepinnten Beitrags wird im Inline-Script des Editors maskiert (`JSON_HEX_TAG`). Ein Titel wie `<!--<script>` konnte unter WordPress < 7.0 nachfolgende Scripts und damit den Editor blockieren.
* Toten Fallback auf `wp.editPost` entfernt.
* Lizenzangabe im Plugin-Header ergänzt.
* Dokumentiert: Der gepinnte Beitrag steht auch im Archiv der Elternkategorie vorn.

= 1.1.0 =
* Pin wird in der Option `shft_pp_pinned_id` statt als Post-Meta gespeichert. Ein bestehender Pin aus 1.0 wird beim ersten Laden übernommen, die alten Meta-Einträge werden gelöscht.
* REST-Feld `shft_pinned` ersetzt das Meta `_shft_pinned` und ist nicht mehr öffentlich lesbar (nur Kontext `edit`).
* Klare Fehlermeldung, wenn bereits ein anderer Beitrag gepinnt ist.
* Pin wird auch beim endgültigen Löschen eines Beitrags entfernt.
* Funktionen im Namespace `Shft\PinPost` statt globaler Funktionen mit `function_exists`-Wrapper.

= 1.0.0 =
* Erste Version.

== Upgrade Notice ==

= 1.1.0 =
Speicherort des Pins geändert. Ein vorhandener Pin wird automatisch übernommen.
