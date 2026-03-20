=== Zalomení ===
Contributors: honza.skypala
Donate link: http://www.honza.info
Tags: grammar, Czech
Requires at least: 4.0
Tested up to: 6.2
Stable tag: 1.5

This plugin helps to keep some grammar rules in Czech language related to word wrapping, e.g. prepositions 'k', 's', 'v' and 'z' cannot be placed at the end of line.


== Description ==

For English, see below.

Czech: Upravujeme-li písemný dokument, radí nám Pravidla českého pravopisu nepsat neslabičné předložky v, s, z, k na konec řádku, ale psát je na stejný řádek se slovem, které nese přízvuk (např. ve spojení k mostu, s bratrem, v Plzni, z&nbsp;nádraží). Typografické normy jsou ještě přísnější: podle některých je nepatřičné ponechat na konci řádku jakékoli jednopísmenné slovo, tedy také předložky a spojky a, i, o, u;. Někteří pisatelé dokonce nechtějí z estetických důvodů ponechávat na konci řádků jakékoli jednoslabičné výrazy (např. ve, ke, ku, že, na, do, od, pod).

<a href="http://prirucka.ujc.cas.cz/?id=880" title="Více informací k problematice">Více informací</a> na webu Ústavu pro jazyk český, Akademie věd ČR.

Tento plugin řeší některé z uvedených příkladů: v textu nahrazuje běžné mezery za pevné tak, aby nedošlo k zalomení řádku v nevhodném místě.

English: This plugin helps to keep some grammar rules in Czech language related to word wrapping, e.g. prepositions 'k', 's', 'v' and 'z' cannot be placed at the end of line.


== Installation ==

1.	Nahrajte kompletní adresář pluginu do wp-content/plugins.
2.	Aktivujte plugin Zalomení v administraci plug-inů.
3.	V Nastavení->Zobrazování můžete nastavit jednotlivé volby.

== Frequently Asked Questions ==

Tento plugin se aplikuje na řadu filtrů WordPressu -- obsah příspěvku, název příspěvku, název celého webu atd. Konkrétně se jedná o tyto filtry:

* comment_author
* term_name
* link_name
* link_description
* link_notes
* bloginfo
* wp_title
* widget_title
* term_description
* the_title
* the_content
* the_excerpt
* comment_text
* single_post_title
* list_cats

Některé uživatelské instalace WordPressu s tím mohou mít problém. Například se může jednat o e-shop, který používá název příspěvku jako název produktu a v něm potřebuje, aby Zalomení nebylo aplikováno; jinak chce ovšem nadále Zalomení používat.

Proto přináší plugin Zalomení svůj vlastní filtr <em>zalomeni_filtry</em>. Můžete si pak do své šablony nebo do svého webu přidat funkci, v které ze seznamu filtrů odstraníte ten, u kterého nechcete Zalomení použít. Příklad zrušení aplikace Zalomení na název příspěvku: 

<code>add_filter('zalomeni_filtry', 'remove_title_from_zalomeni');
function remove_title_from_zalomeni(array $filters) {
  unset($filters['the_title']);
  return $filters;
}</code>

Poznámka: tímto způsobem můžete filtry nejen odebírat, ale také přidávat, pokud to potřebujete.

== Screenshots ==

1. Konfigurace pluginu
2. Příklad

== Changelog ==

= 1.5 =
* bug fix: kompatibilita s PHP 8+
= 1.4.7 =
* bug fix: ošetřeny strict standards v nastavení pluginu
= 1.4.6 =
* bug fix: plugin identifikoval a zpracovával prázdné řetězce jako HTML tagy, což generovalo PHP notice při zapnutém logování; ošetřeno
= 1.4.5 =
* bug fix: zalomení mezi číslem a jednotkou nefungovalo na konci řádku, resp. pokud následovala uzavírací závorka
= 1.4.4 =
* bug fix
= 1.4.3 =
* Ošetřen stav, kdy nejsou v databázi z nějakého důvodu uložena nastavení, v tomto případě se nyní použije výchozí nastavení. (uživatelům se chyba projevovala tak, že nastavení se zobrazovalo bez jakýchkoliv popisků, nešlo uložit a plug-in nefungoval)
= 1.4.2 =
* Zlepšena kompatibilita s utf8 (díky Pavel Krejčí)
= 1.4.1 =
* Kontrola při aktivaci pluginu na PHP verze 5.3 nebo vyšší
* Drobné optimalizace
= 1.4 =
* Zalomení po řadové číslovce nyní podporuje číslovku jako navazující slovo; takto je zajištěno nezalomení např. u data zapsaného ve formátu 1. 1. 2014
* Nová funkcionalita: zabránění zalomení mezi číslovkou a jednotkou nebo měnou (např. 1 m, 5 kg, 50 Kč)
* Nová funkcionalita: zabránění zalomení v měřítkách a poměrech (např. 1 : 1000)
* Vlastní filtr <em>zalomeni_filtry</em> -- umožňuje odebrat nebo přidat filtry, na které se Zalomení aplikuje
* Drobné optimalizace
= 1.3 =
* Změna licence
* Změna ukládání nastavení (interní; původně pole proměnných, nyní jednotlivé proměnné samostatně, snad to vyřeší problémy některých uživatelů s ukládáním nastavení)
* Nová funkcionalita: zabránění zalomení po řadové číslovce (včetně data, např. 1. ledna)
* Nová funkcionalita: uživatelsky definované termíny, které nesmějí být zalomeny
* Screenshoty přesunuty do adresáře assets, aby se zbytečně nestahovaly uživatelům do jejich instalací WordPressu
* Plug-in předělán na PHP třídu, pro lepší izolaci a přehlednost
* WordPress již nevolá activation-hook při aktualizaci pluginu na novou verzi; aktualizace testována a volána v rámci admin_init()
= 1.2.4 =
* Dvojité volání nahrazovací funkce, plugin nefungoval pro dvě příslušná slova nacházející se za sebou (např. pokud by byly zapnuty pevné mezery za předložkami i za spojkami, pak ve výrazu "a s někým" by došlo k nahrazení mezery za "a", ale již ne za "s")
* Nastavení pluginu přemístěno na stránku Nastavení->Zobrazování, je zbytečné, aby měl plugin celou vlastní stránku s nastavením
= 1.2.3 =
* Opraveno volání funkce add_options_page tak, aby nepoužívalo již nepodporovaný formát.
= 1.2.2 =
* Opravena chyba v konfiguraci.
= 1.2.1 =
* Opravena chyba v HTML kódu konfigurace pluginu.
= 1.2 =
* Kompatibilita s WordPress 2.9
= 1.1 =
* Nyní umí vložit pevnou mezeru také za předložku (či jiné slovo), které se nachází na následujících pozicích: první slovo za otevírací závorkou, první slovo po nějakém tagu (např tag pro zapnutí italiky či tučného písma), na začátku odstavce.
* Rozšířen výchozí seznam zkratek, za něž se vkládá mezera
* Nahrazuje mezery v číslech za pevné mezery (např. v telefonním čísle zapsaném jako 800 123 456 nahradí mezery za pevné mezery, aby nebylo číslo rozděleno zalomením řádku).
* Interně přepsáno, již nevyužívá stávající filter wptexturize(), ale přidává vlastní filtr.
= 1.0 =
* Initial release.

== Licence ==

WTFPL License 2.0 applies

<code>           DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
                   Version 2, December 2004

Copyright (C) 2004 Sam Hocevar <sam@hocevar.net>

Everyone is permitted to copy and distribute verbatim or modified
copies of this license document, and changing it is allowed as long
as the name is changed.

           DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
  TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION

 0. You just DO WHAT THE FUCK YOU WANT TO.</code>