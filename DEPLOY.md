# Portfolio deployen (silas-krummen.de)

Der Server betreibt das Projekt mit Docker Compose aus `compose.yml` + `compose.prod.yml`:

| Container | Was er vom Projekt sieht | Folge für Updates |
|---|---|---|
| `php` | ganzes Projekt als Bind-Mount (`.:/var/www/html`) | Code-Änderungen sind nach `git pull` sofort drin, nur der **Symfony-Cache** muss geleert werden. Änderungen am **Dockerfile** brauchen einen Neubau. |
| `nginx` | nur `public/` und **eine einzelne Datei** `docker/nginx.prod.conf` | Siehe unten: nach Änderungen an der nginx-Konfiguration den Container **neu erstellen**, ein `nginx -s reload` reicht nicht. |
| `civ` | eigenes Projekt `../civ-abend` | bleibt bei Portfolio-Updates unberührt, siehe `CIV-DEPLOY.md` |

> **Warum `reload` nicht reicht:** Docker bindet eine einzelne Datei über ihren Inode ein. `git pull` ersetzt die Datei durch eine neue (neuer Inode), der laufende nginx-Container sieht weiter die **alte** Version. Erst ein neu erstellter Container hängt die neue Datei ein.

Alle Befehle unten laufen auf dem Server im Projektordner. Zur Abkürzung einmal pro Sitzung:

```bash
cd ~/bewerbung
alias dc='docker compose -f compose.yml -f compose.prod.yml'
```

## Standard-Ablauf

**0. Lokal:** Änderungen committen und pushen.

**1. Stand holen**

```bash
git status          # sollte sauber sein – lokale Änderungen auf dem Server vorher sichern oder verwerfen
git pull
```

**2. PHP-Image neu bauen** (nur nötig, wenn sich das `Dockerfile` geändert hat – schadet sonst aber nicht)

```bash
dc build php
```

**3. nginx-Konfiguration testen, bevor sie live geht**

```bash
dc run --rm --no-deps nginx nginx -t
```

Startet einen Wegwerf-Container mit der **neuen** Datei im selben Docker-Netz und prüft nur die Syntax.
Erwartet: `syntax is ok` und `test is successful`. Bei einem Fehler hier abbrechen – der Live-Server läuft unverändert weiter.

**4. Container neu erstellen**

```bash
dc up -d --force-recreate php nginx
```

Dauert ein paar Sekunden, in denen die Seite (und die CIV-Subdomains, die am selben nginx hängen) kurz nicht erreichbar sind.

**5. Symfony-Cache leeren** (neue Routen und Templates werden im Prod-Modus sonst nicht erkannt)

```bash
dc exec php php bin/console cache:clear
```

**6. Kurz prüfen**

```bash
dc ps                                            # alle Container "running"/"Up"
dc logs --tail=30 nginx php                      # keine Fehler
curl -sI https://silas-krummen.de/ | head -1     # HTTP/2 200 bzw. HTTP/1.1 200
```

Im Browser einmal mit **Strg+Shift+R** neu laden: CSS und JS haben keine Versionsnummer im Dateinamen, der Browser hält sonst eventuell die alte Version.

## Diesmal zusätzlich prüfen (Release 10/2026)

Was sich geändert hat: Zeugnisse hinter dem Login, Schriften selbst gehostet, Sicherheits-Header, `expose_php` aus, Parallax-Hintergrund und weiches Scrollen (Lenis).

**a) Zeugnisse liegen nicht mehr öffentlich**

```bash
ls documents/                 # die 7 PDFs
ls public/documents 2>&1      # "No such file or directory" – sonst Ordner löschen, er wäre weiter öffentlich
```

```bash
# ohne Login: muss auf die Login-Seite umleiten (302 … Location: …/portfolio/login), NICHT 200 mit application/pdf
curl -sI https://silas-krummen.de/documents/ihk-zeugnis.pdf | grep -iE "^HTTP|^location|^content-type"
```

Im Browser eingeloggt: **Dokumente** öffnen, Vorschau, „In neuem Tab öffnen“ und „Herunterladen“ einmal am PC und einmal am Handy testen.

**b) Header**

```bash
curl -sI https://silas-krummen.de/ | grep -iE "strict-transport|content-security|x-content-type|referrer-policy|permissions-policy|x-frame|^server|x-powered-by"
```

Erwartet: die fünf Sicherheits-Header und `X-Frame-Options`, `Server: nginx` **ohne** Versionsnummer und **kein** `X-Powered-By`.

**c) Keine Anfragen mehr an Google**

Im Browser DevTools → Netzwerk, Seite neu laden: keine Einträge von `fonts.googleapis.com` oder `fonts.gstatic.com`, die Schriften kommen aus `/fonts/…`.

**d) Content-Security-Policy hat nichts kaputt gemacht**

DevTools → Konsole auf diesen Seiten offen lassen, es darf keine Meldung „Refused to … because it violates the Content Security Policy“ kommen:
Start, Über mich (Timeline, KI-Tabs), Projekte, alle drei Projektseiten (Video, Lightbox), Dokumente, LAN-Night (Bildschirm, Admin, QR-Code am Handy), CIV-Abend.

Falls doch etwas blockiert wird: in `docker/nginx.prod.conf` die Zeile `add_header Content-Security-Policy …` auskommentieren, dann Schritte 3 und 4 wiederholen. Die übrigen Header können bleiben.

**e) APP_SECRET und Umgebung**

```bash
dc exec php php bin/console debug:dotenv
```

* `APP_ENV` muss `prod` sein.
* Bei `APP_SECRET` sollte als Quelle `.env.local` stehen, **nicht** `.env`. Steht dort `.env`, nutzt der Server den Wert, der im Git-Repo liegt. Dann einen neuen setzen:

```bash
echo "APP_SECRET=$(openssl rand -hex 32)" >> .env.local
dc exec php php bin/console cache:clear
```

  Folge: alle bestehenden Logins laufen ab, man muss sich einmal neu anmelden. Sonst nichts.

* `.env.dev` mit seinem Secret wird im Prod-Modus gar nicht geladen und spielt online keine Rolle.

## Wenn etwas schiefgeht

| Symptom | Ursache / Lösung |
|---|---|
| nginx startet nicht (`dc ps` zeigt `Exited`) | `dc logs nginx` lesen. Schritt 3 übersprungen? Fehler in der Datei beheben, Schritt 3 + 4. |
| Fehler 500 nach dem Update | `dc logs php` und `var/log/prod.log`. Meist Cache: Schritt 5 wiederholen. Meldet der Cache „Permission denied“: `dc exec php chown -R www-data:www-data var` |
| Dokumente geben 404 | Liegen die PDFs in `documents/` (nicht `public/documents/`)? Dateiname muss in `src/Domain/Document/lib/documents.php` stehen. |
| Änderungen an nginx wirken nicht | Container wurde nur neu geladen statt neu erstellt – Schritt 4. |
| Seite sieht alt/kaputt aus | Browser-Cache: Strg+Shift+R. |

**Zurück auf den vorherigen Stand:**

```bash
git log --oneline -5          # Commit vor dem Update heraussuchen
git checkout <commit>         # oder: git revert <commit>
dc build php && dc up -d --force-recreate php nginx
dc exec php php bin/console cache:clear
```

Danach mit `git checkout main` wieder auf den aktuellen Stand zurück, sobald der Fehler behoben ist.
