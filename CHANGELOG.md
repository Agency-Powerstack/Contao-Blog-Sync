# Changelog

## [1.0.0] – 2025-02-27

### Added

- Blog-Push-Webhook-Endpunkt (`/contao/blog-sync/api/push`) mit Bearer-Token-Authentifizierung
- OAuth-ähnlicher Connect-Flow mit Agency Powerstack (`/contao/blog-sync/callback`)
- Disconnect-Endpunkt (`/contao/blog-sync/disconnect`)
- Automatischer Download und DBAFS-Registrierung von Bildern aus importierten Blog-Posts
- Erstellung nativer Contao Content-Elemente (HTML + Bild) aus Blog-Inhalten
- Sync-Protokoll im Contao-Backend (Tabelle `tl_blog_sync_log`)
- Backend-Modul „Agency Powerstack → Accounts" zur Verwaltung von Verbindungen
- Benachrichtigung des Agency-Powerstack-Backends beim lokalen Löschen einer Verbindung
