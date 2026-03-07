# Union Citoyenne (Node.js)

Site Node.js (Express + EJS + PostgreSQL + Resend).

## Lancer en local

1. Renseigner les variables dans `.env`
2. Installer les deps: `npm install`
3. Démarrer: `npm start`

## Variables importantes

- `DATABASE_URL`
- `APP_SECRET`
- `MAILER_FROM`
- `RESEND_API_KEY` (ou `MAILER_DSN` au format `resend+api://KEY@default`)
- `APP_BASE_URL` (optionnel, sinon domaine de la requête utilisé)
- `RESET_SUPPORTERS_ON_BOOT` (`true` une fois pour reset la table, puis `false`)

## Routes

- `/`
- `/charte`
- `/adherer`
- `/adherer/confirmation-envoyee`
- `/adherer/confirmer/:token`
