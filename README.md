# Websitebeheer 1.1.0

Zelfstandige WordPress-plugin van emonks voor een klantdashboard, algemene handleidingen, bureauprofiel, supportgegevens, beheerfooter en inlogvormgeving. Werkt zonder ACF, Timber of Skeletor. PHP 8.0+ en WordPress 6.2+.

## Installeren en bijwerken

1. Installeer de map `websitebeheer` in `wp-content/plugins`, of upload `emonks-dashboard-1.1.0.zip` via Plugins → Nieuwe plugin → Plugin uploaden.
2. Deactiveer de oude plugin Emonks Custom Dashboard als die actief is.
3. Activeer Websitebeheer. Bij een oudere Skeletor-versie met het ingebouwde dashboard wacht de plugin met starten en toont een melding; zo ontstaan geen dubbele dashboards.
4. Werk Skeletor bij naar 1.2.0 of nieuwer. Bij andere thema’s is deze stap niet nodig.
5. Controleer Dashboard, Handleidingen, Instellingen → Bureau & whitelabel en het inlogscherm als beheerder én als redacteur.

Skeletor voegt zijn eigen uitleg en links toe. Bij een ander thema blijven alleen de algemene WordPress-handleidingen over. Bij blokthema’s verschijnt geen uitleg voor de klassieke menu-editor.

## Bestaande gegevens

De optienaam `skeletor_agency` is bewust behouden: bestaande bureaugegevens, logo, supportstatus en bewust lege velden blijven op hun plaats. Er is geen kopieermigratie en activeren schrijft deze gegevens niet opnieuw weg. De plugin bewaart het profiel via de WordPress Settings API, uitsluitend voor `manage_options` en met noncecontrole.

Websitevermelding en ACF-velden blijven de verantwoordelijkheid van het thema. Skeletor bewaart eigen en bewust lege footerwaarden. Bij een ander thema verschijnt de instelling voor Skeletors subfooter niet; een bestaande keuze blijft bewaard bij opslaan van het bureauprofiel.

Deactiveren verwijdert geen gegevens en geeft WordPress zijn standaarddashboard en inlogvormgeving terug. Skeletor blijft werken en gebruikt bij uitgeschakelde plugin de eerder opgeslagen eigen footerinhoud. Een automatische of verborgen bureauvermelding wordt dan niet meer toegepast. Verwijderen van de plugin wist het bureauprofiel evenmin. Voor terugkeer naar een oude Skeletor-versie: deactiveer de plugin; de oude themacode leest dezelfde optie.

## Uitbreiden vanuit een thema of plugin

- `websitebeheer_allowed` — boolean voor toegang tot dashboard en hulp; basis is `edit_posts` of `edit_pages`.
- `websitebeheer_content_types` — geregistreerde openbare inhoudstypen, daarna beperkt tot zichtbare typen die de gebruiker mag bewerken.
- `websitebeheer_actions` — snelkoppelingen met `title`, `text`, `url` en Dashicon-suffix `icon`; uitbreidingen controleren zelf gebruikersrechten.
- `websitebeheer_guides` — uitleg per slug met `title`, `duration`, `intro`, `steps` (titel/tekst-paren), `check`, `note` en optioneel `capability`. De plugin filtert daarna op rechten en beschikbare onderdelen.
- `websitebeheer_guide_actions` — lijst met `url` en `title`, plus de handleidingslug als tweede argument. Controleer rechten in de uitbreiding.
- `websitebeheer_screen_guide` — handleidingslug en het huidige scherm als tweede argument.
- `websitebeheer_default_credit_mode` — standaard `custom`; een thema kan de oude instelling herkennen.
- `websitebeheer_agency_settings` — actie binnen het beveiligde instellingenformulier, ontvangt het gesaneerde bureauprofiel. Gebruikt door Skeletor voor de websitevermelding.

`Emonks\Websitebeheer\Agency::profile()` levert het bureauprofiel; `Agency::credit()` levert de ingestelde vermelding of `null` om eigen inhoud te behouden. `Workspace::help_url($slug)` levert de hulplink.

De klassen worden tijdens het laden van de plugin ingelezen. Registratie van schermen en branding gebeurt op `after_setup_theme` met prioriteit 20, nadat het thema zijn uitbreidingen heeft geregistreerd.

## Onderhoud en uitgave

De bron staat in deze pluginmap, los van het thema. Bewerk de leesbare CSS en JavaScript in `assets/`; er zijn geen minified bestanden nodig. Een pluginwijziging krijgt een eigen hogere versie in de pluginheader en `WEBSITEBEHEER_VERSION`, plus concrete notities in CHANGELOG.md. Bouw het runtimepakket met `python3 scripts/build-zip.py`; de GitHub-releaseworkflow gebruikt dezelfde pakketbouwer. Een themarelease bevat deze plugin niet: installeer en update hem afzonderlijk, ook bij Site Factory-publicaties.

Algemene uitleg staat in `includes/customer-guides.php`; thema-uitleg hoort bij de betreffende thema-integratie. Geen externe diensten of tracking, geen wijzigingen aan rollen en geen verwijdering van widgets van andere plugins of Sitediagnose.

Controle zonder thema: `wp --skip-themes eval-file wp-content/plugins/websitebeheer/tests/test-standalone.php` vanuit de WordPress-root. Skeletor-integratie: `wp eval-file wp-content/themes/skeletor/tests/test-workspace.php`.

## Updates via WordPress en GitHub Releases

Vanaf 1.1.0 controleert de ingebouwde Plugin Update Checker 5.7 op stabiele releases in [jeffreypisa/emonks-dashboard](https://github.com/jeffreypisa/emonks-dashboard). De repository is openbaar; er is geen token of extra updaterplugin nodig. WordPress toont updates op de pluginpagina en bij Dashboard → Updates. Bij Multisite werk je plugins bij via Netwerkbeheer. Automatische updates worden niet ongevraagd ingeschakeld.

Installeer deze eerste versie met updater één keer handmatig met `dist/emonks-dashboard-1.1.0.zip`. Eerdere versies herkennen GitHub-updates nog niet. Behoud het hoofdbestand `websitebeheer.php` en vervang dezelfde bestaande plugin; activeer geen tweede kopie ernaast. De updater behoudt bij volgende updates de bestaande pluginmap, ook als die van de repositorynaam afwijkt.

Nieuwe releases maken:

1. Verhoog het versienummer in de pluginheader en `WEBSITEBEHEER_VERSION`; werk de wijzigingsnotities bij en voer `php tests/update-policy.php` en de overige relevante tests uit.
2. Commit en push de wijziging. Maak daarna een tag `vX.Y.Z` die exact bij het pluginversienummer hoort en push die tag.
3. De GitHub Actions-workflow `Publish WordPress release` controleert de tag, test de updater en bouwt/publiceert het installatiebestand als GitHub Release. Bekijk of de workflow geslaagd is. Ontbreekt een release of de bijbehorende installatie-zip, dan verschijnt geen update in WordPress.

De updater gebruikt uitsluitend gepubliceerde stabiele releases met een passende installatie-zip. Een gewone branchpush, een losse tag, een conceptrelease, een prerelease of de automatisch gegenereerde GitHub-broncode-zip wordt niet als update gebruikt. WordPress controleert periodiek; via de extra link voor updatecontrole op de pluginpagina kun je eerder controleren. GitHub-storingen en API-limieten kunnen de controle tijdelijk verhinderen.

De MIT-licentie en bronbestanden van Plugin Update Checker staan onder `includes/plugin-update-checker`. Bewerk die externe bibliotheek niet handmatig; vervang haar bij een toekomstige bibliotheekupdate door een gecontroleerde officiële uitgave.

Gebruik de Git-repository als ontwikkelbron. Installeer de release-zip op de WordPress-omgevingen die je via de pluginpagina wilt bijwerken; een WordPress-update vervangt de pluginmap en kan daardoor een daarin aanwezige `.git`-map verwijderen.

Updatercontroles: `php tests/update-policy.php` werkt zonder database en netwerk. `wp eval-file tests/updater-wordpress.php` test de echte WordPress-updategegevens en het behouden van de pluginmap met onderschept HTTP-verkeer en tijdelijke bestanden.
