# CIV-Abend online (civ.silas-krummen.de / civ-admin.silas-krummen.de)

Der CIV-Abend läuft als eigener Container (`civ`) neben dem Portfolio und hängt hinter demselben nginx.
Jeder Aufruf der beiden Subdomains wird per `auth_request` beim Symfony-Login geprüft:

| Adresse | Zweck | Login |
|---|---|---|
| `https://civ.silas-krummen.de` | Bildschirm | normales Portfolio-Passwort |
| `https://civ-admin.silas-krummen.de` | Spielleitung | Benutzer `civ_admin` (eigenes Passwort) |

Im Portfolio unter **Projekte**: Karte „CIV-Abend Spielleitung“ **3× schnell anklicken → Bildschirm**, **5× → Spielleitung**.

## Einmalig einrichten

**1. DNS** – zwei A-Records (oder CNAME auf `silas-krummen.de`) auf die Server-IP:
`civ.silas-krummen.de` und `civ-admin.silas-krummen.de`.

**2. CIV-Projekt auf den Server kopieren** – in denselben Ordner, in dem `bewerbung/` liegt
(anderer Ort: `CIV_DIR=/pfad/zu/civ-abend` vor die docker-compose-Befehle setzen):

```bash
# von Windows aus, Pfad/Benutzer anpassen
scp -r C:\Users\silas\PhpstormProjects\civ-abend user@server:~/
```

`data/state.json` wird dabei mitkopiert – wer online frisch starten will, löscht sie auf dem Server.

**3. Portfolio aktualisieren und starten**

```bash
cd ~/bewerbung
git pull
docker compose -f compose.yml -f compose.prod.yml up -d --build
docker compose -f compose.yml -f compose.prod.yml exec php php bin/console cache:clear
```

Ab hier laufen beide Subdomains schon, zeigen aber bis Schritt 4 eine Zertifikatswarnung.

**4. Zertifikat um die beiden Subdomains erweitern**

```bash
docker run --rm \
  -v "$PWD/certbot/conf:/etc/letsencrypt" -v "$PWD/certbot/www:/var/www/certbot" \
  certbot/certbot certonly --webroot -w /var/www/certbot \
  --cert-name silas-krummen.de --expand \
  -d silas-krummen.de -d www.silas-krummen.de -d civ.silas-krummen.de -d civ-admin.silas-krummen.de

docker compose -f compose.yml -f compose.prod.yml exec nginx nginx -s reload
```

## Passwort der Spielleitung ändern

```bash
docker compose -f compose.yml -f compose.prod.yml exec php php bin/console security:hash-password
```

Den ausgegebenen Hash in `config/packages/security.yaml` beim Benutzer `civ_admin` eintragen, dann `cache:clear` (siehe oben).

## Am Abend

* Fernseher: Chrome so starten, dann läuft der Ton ohne Klick:
  `chrome.exe --autoplay-policy=no-user-gesture-required --kiosk https://civ.silas-krummen.de`
  Einmal mit dem Portfolio-Passwort einloggen.
* Handy: Projekte → Karte 5× antippen → mit dem `civ_admin`-Passwort einloggen.
* Bilder/Musik der Events liegen auf dem Server in `civ-abend/src/public/events/…` (kein Neustart nötig).

## Was wofür zuständig ist

* `docker/nginx.prod.conf` – Server-Blöcke für die beiden Subdomains, `auth_request` → `/portfolio/civ-auth/{display|admin}`
* `src/Domain/Civ/Controller/CivController.php` – Login-Prüfung für nginx und Weiterleitung nach dem Login
* `config/packages/security.yaml` – Benutzer `civ_admin` (Rolle `ROLE_CIV_ADMIN`)
* `config/packages/framework.yaml` – Session-Cookie für `.silas-krummen.de` (über `SESSION_COOKIE_DOMAIN`, siehe `compose.prod.yml`)
* `compose.prod.yml` – Container `civ` (ohne eigenen Port, nur über nginx erreichbar)
