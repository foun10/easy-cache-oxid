<?php

$sLangName = 'Deutsch';

$aLang = [
    'charset' => 'UTF-8',

    // Beschriftungen für den Standard-Reiter der Moduleinstellungen. Das Modul
    // bringt eine eigene Einstellungsseite mit (EC_* oben), die Settings stehen
    // aber in der metadata.php und tauchen damit auch dort auf - ohne diese
    // Schlüssel zeigt OXID die rohen Bezeichner und meldet die fehlende
    // Gruppenüberschrift als Fehler.
    'SHOP_MODULE_GROUP_foun10EasyCache' => 'EasyCache',
    'SHOP_MODULE_foun10EasyCacheEnabled' => 'Cache aktivieren',
    'SHOP_MODULE_foun10EasyCacheTTL' => 'Cache-Lebensdauer (TTL) in Sekunden',
    'SHOP_MODULE_foun10EasyCacheWhitelist' => 'Gecachte Controller (Whitelist)',
    'SHOP_MODULE_foun10EasyCacheSaveStats' => 'Statistiken speichern',
    'SHOP_MODULE_foun10EasyCacheGzip' => 'Cache-Dateien komprimieren (gzip)',
    'SHOP_MODULE_foun10EasyCacheMinify' => 'HTML minifizieren (nur Whitespace)',

    'FOUN10_MODULES' => 'foun10 Module',
    'FOUN10_EASYCACHE' => 'EasyCache',
    'FOUN10_EASYCACHE_SETTINGS' => 'Einstellungen',
    'FOUN10_EASYCACHE_STATS' => 'Statistik',
    'FOUN10_EASYCACHE_CLEAR' => 'Cache leeren',

    'EC_INTRO_TEXT' => 'EasyCache erstellt für ausgewählte Shop-Seiten eine statische HTML-Kopie und liefert diese bei weiteren Aufrufen direkt aus, ohne die komplette Shop-Logik erneut auszuführen. Zwischengespeichert werden ausschließlich die in der Konfiguration hinterlegten Controller (z. B. Startseite, Artikellisten, Artikeldetails, Content-Seiten) - alle anderen Seiten wie Checkout, Konto oder Bestellungen laufen immer normal. Der Cache greift außerdem nur bei nicht eingeloggten Besuchern mit leerem Warenkorb: sobald ein Kunde eingeloggt ist oder Artikel im Warenkorb liegen hat, wird die Seite immer live gerendert und nie zwischengespeichert oder aus dem Cache bedient. Die Statistik unten zeigt, wie oft eine Seite aus dem Cache ausgeliefert (Treffer) oder neu erzeugt (Fehlschlag) wurde.',

    'EC_STATUS_ENABLED' => 'Aktiv',
    'EC_STATUS_DISABLED' => 'Inaktiv',

    'EC_SECTION_GENERAL' => 'Grundeinstellungen',
    'EC_LABEL_ENABLED' => 'Cache aktivieren',
    'EC_LABEL_TTL' => 'Cache-Lebensdauer (TTL)',
    'EC_HINT_TTL' => 'Gültigkeitsdauer eines Cache-Eintrags in Sekunden, bevor er automatisch neu erzeugt wird (unabhängig von gezieltem Leeren per Tag). Beispiele: 3600 = 1 Stunde, 14400 = 4 Stunden, 21600 = 6 Stunden, 43200 = 12 Stunden, 86400 = 24 Stunden.',
    'EC_LABEL_WHITELIST' => 'Gecachte Controller (Whitelist)',
    'EC_HINT_WHITELIST' => 'Kommagetrennte Liste der Controller-Klassenschlüssel, die gecacht werden dürfen. Was hier nicht steht, wird nie gecacht. Standard: start, alist, details, content. Ein leeres Feld beendet das Caching, ohne das Modul abzuschalten.',
    'EC_HINT_DEPLOY' => 'Hinweis: In manchen Setups wird die Modulkonfiguration aus der Versionsverwaltung ausgerollt - ein Deployment kann die auf dieser Seite gesetzten Werte dann überschreiben. Trifft das auf diesen Shop zu, sollten die Werte in der ausgerollten Konfiguration gepflegt werden.',
    'EC_LABEL_SAVE_STATS' => 'Statistiken speichern',
    'EC_HINT_SAVE_STATS' => 'Erfasst bei jeder Frontend-Anfrage einen Cache-Treffer bzw. -Fehlschlag je Controller. Verursacht zusätzliche Datenbankschreibzugriffe - nur bei Bedarf aktivieren.',
    'EC_LABEL_GZIP' => 'Cache-Dateien komprimieren (gzip)',
    'EC_HINT_GZIP' => 'Reduziert den Speicherbedarf der Cache-Dateien auf der Festplatte deutlich (typisch 75-85%). Ein Wechsel dieser Einstellung erfordert kein manuelles Leeren des Caches - alte Dateien werden einfach nicht mehr verwendet und verschwinden mit der Zeit.',
    'EC_HINT_GZIP_UNAVAILABLE' => 'Die PHP-Erweiterung "zlib" ist auf diesem Server nicht verfügbar. Diese Einstellung hat aktuell keine Wirkung, auch wenn sie aktiviert ist.',
    'EC_HINT_MINIFY_UNAVAILABLE' => 'Das optionale Paket "voku/html-min" ist nicht installiert. Die Einstellung bleibt daher wirkungslos - mit "composer require voku/html-min" im Shop nachinstallieren.',
    'EC_LABEL_MINIFY' => 'HTML minifizieren (nur Whitespace)',
    'EC_HINT_MINIFY' => 'Entfernt überflüssige Leerzeichen/Zeilenumbrüche aus dem gecachten HTML, bevor es gespeichert wird - reduziert die Dateigröße zusätzlich zu gzip deutlich, ohne sichtbare Inhalte, Skripte oder Formatierungen zu verändern. Ein Wechsel dieser Einstellung erfordert kein manuelles Leeren des Caches - bereits gespeicherte Seiten werden einfach mit der Zeit durch neue ersetzt.',
    'EC_BUTTON_SAVE' => 'Speichern',
    'EC_MSG_SAVED' => 'Einstellungen gespeichert.',

    'EC_SECTION_CACHE_MANAGEMENT' => 'Cache-Verwaltung',
    'EC_HINT_CLEAR_CACHE' => 'Entfernt alle zwischengespeicherten Seiten von der Festplatte und setzt die Statistik zurück.',
    'EC_BUTTON_CLEAR_CACHE' => 'Cache leeren',
    'EC_CONFIRM_CLEAR_CACHE' => 'Cache wirklich vollständig leeren?',
    'EC_MSG_CACHE_CLEARED' => 'Cache geleert.',
    'EC_MSG_CACHE_CLEARED_FILES' => 'Dateien entfernt',
    'EC_FILE_STATS_LABEL' => 'Aktuell im Cache',
    'EC_FILE_STATS_FILES' => 'Dateien',
    'EC_HINT_COUNT_FILES' => 'Durchsucht das Cache-Verzeichnis vollständig auf der Festplatte - bei sehr vielen Dateien kann das kurz dauern. Wird nicht automatisch beim Laden dieser Seite ausgeführt.',
    'EC_BUTTON_COUNT_FILES' => 'Cache-Dateien zählen',

    'EC_SECTION_STATS' => 'Cache-Statistik',
    'EC_STATS_DISABLED_HINT' => 'Die Statistik-Erfassung ist deaktiviert. Aktivieren Sie oben "Statistiken speichern", um Daten zu sammeln.',
    'EC_STATS_EMPTY' => 'Es liegen noch keine Statistikdaten vor.',
    'EC_TABLE_VIEWCLASS' => 'Ansicht (FOUN10VIEWCLASS)',
    'EC_TABLE_REQUESTS' => 'Anfragen',
    'EC_TABLE_HITS' => 'Treffer',
    'EC_TABLE_MISSES' => 'Fehlschläge',
    'EC_TABLE_RATIO' => 'Trefferquote',
    'EC_TABLE_AVG_HIT_MS' => 'Ø Antwortzeit Treffer',
    'EC_TABLE_AVG_MISS_MS' => 'Ø Antwortzeit Fehlschlag',
    'EC_TABLE_TOTAL' => 'Gesamt',
    'EC_BUTTON_RELOAD_STATS' => 'Aktualisieren',
    'EC_BUTTON_RESET_STATS' => 'Statistik zurücksetzen',
    'EC_CONFIRM_RESET_STATS' => 'Statistik wirklich zurücksetzen?',
    'EC_MSG_STATS_RESET' => 'Statistik wurde zurückgesetzt.',

    'EC_SECTION_CLEAR_ALL' => 'Gesamten Cache leeren',
    'EC_SECTION_CLEAR_START' => 'Startseite leeren',
    'EC_HINT_CLEAR_START' => 'Die Startseite hat keine eigene Artikel-/Kategorie-ID und wird daher nicht automatisch bei Bestandsänderungen mitgeleert - hier lässt sie sich gezielt und manuell aus dem Cache entfernen.',
    'EC_BUTTON_CLEAR_START' => 'Startseite leeren',
    'EC_MSG_TAG_CLEARED' => 'Cache-Eintrag geleert für Tag',

    'EC_SECTION_CLEAR_TAG' => 'Cache für Artikel/Kategorie/Hersteller leeren',
    'EC_HINT_CLEAR_TAG' => 'Nach Titel suchen und ein Ergebnis auswählen, um alle zwischengespeicherten Seiten zu leeren, die diesen Artikel, diese Kategorie oder diesen Hersteller enthalten.',
    'EC_TAGTYPE_PRODUCT' => 'Artikel',
    'EC_TAGTYPE_CATEGORY' => 'Kategorie',
    'EC_TAGTYPE_MANUFACTURER' => 'Hersteller',
    'EC_SEARCH_PLACEHOLDER_PRODUCT' => 'Titel oder Artikelnummer eingeben...',
    'EC_SEARCH_PLACEHOLDER_CATEGORY' => 'Kategoriename eingeben...',
    'EC_SEARCH_PLACEHOLDER_MANUFACTURER' => 'Herstellername eingeben...',
    'EC_SEARCH_EMPTY' => 'Keine Treffer.',
    'EC_SELECTED_LABEL' => 'Ausgewählt',
    'EC_BUTTON_CLEAR_TAG' => 'Ausgewählten Eintrag leeren',
];
