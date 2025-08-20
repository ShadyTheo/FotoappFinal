## Projektplan – Foto-Galerie-Software (Start neu)

Ziel: Eine minimalistische, einfach zu bedienende Web‑Anwendung für Fotokunden, um Galerien privat anzusehen. Fokus: einfache Installation (Docker), klarer Workflow für Fotograf (Admin) und Kunden (Viewer), deutschsprachige UI, Dark/Light‑Modus.

### 1. Produktziele
- Einfach: Installation in 1–2 Befehlen via Docker
- Minimalistisch: Startseite = Login; danach je nach Rolle Weiterleitung
- Sicher: Private Galerien per Zugangscode oder pro Kunde (E‑Mail) sichtbar
- Performance: Keine schweren Frameworks, lokale Bilddateien, SQLite
- Sichtbarkeit des Systemstatus: Upload‑Fortschritt, Ladezustände, Erfolg/Fehler‑Feedback

### 2. Zielgruppen und Rollen
- Admin (Fotograf): erstellt/verwaltet Galerien, lädt Fotos/Videos hoch, teilt Links/Codes
- Kunde (Viewer): sieht nur freigegebene Galerien (privat, per Code zugänglich)

### 3. Architektur & Technik
- Laufzeit: PHP 8.3 + Apache, SQLite als DB, alles in einem Docker‑Container
- Persistenz: Medien im Ordner `public/uploads`, Datenbank als Datei `data/app.sqlite` (Volumes)
- Routing: einfacher PHP‑Router (keine Frameworks) mit Sessions für Login
- Sicherheit: Passwort‑Hashing (PHP password_hash), Session‑Regeneration nach Login, nur benötigte Ports
- Medienwiedergabe:
  - Fotos: direkt als Dateien ausgeliefert
  - Videos: HTML5 `<video>` mit MP4 (H.264/AAC) als MVP; Range‑Requests durch Apache sind möglich
  - Optional (später): Transcoding/Thumbnails via FFmpeg (erhöht Image‑Größe)
- Skalierung: Single‑Container (MVP). Später optional CDN/Proxy/Thumbnails/FFmpeg‑Worker

Verzeichnisstruktur (geplant):
- `public/` – Webroot, `index.php`, statische Assets (CSS/JS)
- `src/` – Bootstrap (DB/Session), Router, Controller‑Funktionen
- `data/` – SQLite‑Datei (als Docker Volume gemountet)
- `infrastructure/` – Apache‑Config, Dockerfiles u.a.
- `docs/` – Dokumentation, dieser Plan

### 4. Datenmodell (MVP)
- `users`: id, email, password_hash, role [admin|client], created_at
- `galleries`: id, name, client_email (optional), access_code (optional), is_public (0/1), created_at
- `media`: id, gallery_id, type [photo|video], filename, mime_type, title (optional), duration_seconds (optional, nur Video), poster_filename (optional, Thumbnail/Poster), uploaded_at
  - Index: `gallery_id`
  - Anmerkung: ersetzt ein separates `photos`/`videos`‑Schema. Einfacher Upload/Anzeige‑Flow mit einem Table

Zulässige Formate (MVP):
- Foto: JPG, PNG (später WebP)
- Video: MP4 (H.264 Video + AAC Audio). MOV/MKV optional später via Transcoding

### 5. Kerndesign (UI/UX)
- Stil: Minimal, barrierearm, responsive, ohne Branding, Dark/Light via `prefers-color-scheme`
- Seitenfluss:
  - Login (E‑Mail/Passwort)
  - Weiterleitung:
    - Admin → Admin‑Dashboard (Galerienliste, Erstellen, Bearbeiten, Hochladen)
    - Client → „Meine Galerien“ (Liste), Galerie‑Ansicht mit Raster (Fotos+Videos)
- Sichtbarkeit des Systemstatus (Heuristik):
  - Upload: sichtbarer Fortschrittsbalken je Datei + Gesamtstatus
  - Aktionen: kurze Erfolg/Fehler‑Hinweise (Toasts/Flash)
  - Laden: Skeleton/Spinner bei Liste/Raster, „Wird geladen…“
  - Video: „Wird verarbeitet…“ (später, wenn Transcoding aktiv)
- Sprache: Deutsch (Texte/Labels)

### 6. Feature‑Plan (iterativ)
- MVP (Iteration 1–2):
  - Login/Logout (DE), Admin‑Dashboard, Galerie anlegen (Name, öffentlich?, Zugangscode, Kundene‑Mail)
  - Upload Medien (Mehrfachupload: Fotos + MP4‑Videos), Galerie ansehen (Raster: Foto‑Thumbs, Video als Poster/Player)
  - Teilen: Link generieren (öffentlich) und Code‑Link (mit `?code=...`)
  - Upload‑Fortschritt (HTML5 progress), Flash‑Feedback bei Erfolg/Fehler
  - Docker‑Setup (Dockerfile + docker-compose), Volumes für `data/` und `uploads/`
- Iteration 3:
  - Galerie „Bearbeiten“: Felder ändern, Codes generieren/ändern, Kundene‑Mail anpassen, Sichtbarkeit
  - Rechte/Prüfungen: Zugang nur für zugeordnete E‑Mail, öffentlich oder Code
  - UI‑Verbesserungen: Toaster, kopierbare Share‑Links, „Link kopiert“ Feedback
- Iteration 4:
  - Thumbnails (Fotos) und Poster (Videos) für schnellere Anzeige
  - Optional: FFmpeg in Container integrieren (Transcoding .mov→.mp4, Poster‑Bild erzeugen)
  - Medien löschen/umbenennen; Galerie löschen
- Iteration 5 (optional):
  - ZIP‑Download, Favoriten/Like, Wasserzeichen, E‑Mail‑Einladungen (SMTP)

### 7. Nicht‑Funktionale Anforderungen
- Installation: „kopieren + `docker compose up -d`“
- Port: HTTP 8080→80 (konfigurierbar)
- Datensicherung: regelmäßiger Backup der Volumes `data` und `uploads`
- Fehleranzeige: Dev‑Modus mit Fehlern, Prod‑Modus reduziert (ENV Variable)
- Uploadlimits: PHP `upload_max_filesize`/`post_max_size` und Apache/Proxy beachten (Konfig in Dockerfile/ENV dokumentieren)

### 8. Installation (Docker, lokal)
Voraussetzung: Docker Desktop installiert.

Befehle (nach Implementierung):
- `docker compose up --build -d`
- App aufrufen: `http://localhost:8080`
- Standard‑Admin (nur im Dev): `admin@example.com` / `admin123` (danach Passwort ändern)

Persistenz (Volumes im `docker-compose.yml`):
- `./data:/var/www/html/data`
- `./public/uploads:/var/www/html/public/uploads`

### 9. Entwicklungs‑Workflow (für uns)
- Schritt 1: Plan freigeben (dieses Dokument)
- Schritt 2: Clean‑Slate Repo (alte Files entfernen/vereinheitlichen)
- Schritt 3: Scaffold (Docker, Apache, Minimal‑CSS/JS, Bootstrap/Router, SQLite Schema)
- Schritt 4: MVP Features implementieren (in kleinen PR‑großen Blöcken)
- Schritt 5: Gemeinsame Review → nächste Iteration

Standards:
- Verständlicher Code (PHP, keine Framework‑Magie), klare Dateistruktur
- Kein sensibles Branding, Texte deutsch, Shortcuts dokumentiert

### 10. Risiken & offene Punkte
- Video‑Transcoding erhöht Image‑Größe/Komplexität → als optionale Iteration
- Bild/Video‑Uploadlimits/Timeouts → klare Doku + sinnvolle Defaults
- Speicherplatz: Uploads können wachsen → regelmäßige Archivierung/S3 (später)
- Sicherheit: Zugangscodes per Link sind „Security by URL“ → optional echte „Kunden‑Benutzer“ (Login) pro Galerie

### 11. Abnahme‑Kriterien MVP
- Login/Logout funktioniert, Rollen werden beachtet
- Admin kann Galerie anlegen, Medien (Foto+MP4) hochladen, Link/Code teilen
- Kunde kann freigegebene Galerie sehen (Fotos anzeigen, Videos abspielen)
- Sichtbarer Upload‑Fortschritt und verständliche Status‑Meldungen
- App läuft stabil via Docker, Daten bleiben über Neustarts erhalten

### 12. Nächste Schritte nach Freigabe
- Repository aufräumen (Start neu)
- Grundgerüst implementieren (Docker + PHP + SQLite + Minimal‑UI)
- MVP‑Features (Iteration 1–2) umsetzen

— Ende —
