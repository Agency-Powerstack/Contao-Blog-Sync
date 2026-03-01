# Agency Powerstack Blog Sync for Contao

Synchronisiert Blog-Beiträge automatisch von [Agency Powerstack](https://agency-powerstack.com) in das Contao News-Archiv via Push-Webhooks.

## Voraussetzungen

- PHP ^8.1
- Contao ^5.0 mit `contao/news-bundle`

## Installation

```bash
composer require agencypowerstack/contao-blog-sync
```

Anschließend die Datenbank migrieren:

```bash
vendor/bin/contao-console contao:migrate
```

Alternativ: Installation und Datenbankaktualisierung über den Contao Manager.

### Account verknüpfen

1. Im Contao-Backend zu **Agency Powerstack → Accounts** navigieren
2. Auf **Neuen Account anlegen** klicken
3. Du wirst zu Agency Powerstack weitergeleitet, um die Verbindung zu autorisieren
4. Nach der Autorisierung erscheint der neue Account in der Liste
5. Account bearbeiten und ein **Nachrichtenarchiv** wählen, in das importierte Beiträge abgelegt werden

## Lizenz

Alle Rechte vorbehalten. © Agency Powerstack
