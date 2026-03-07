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

## Routes

- `/`
- `/charte`
- `/adherer`
- `/adherer/confirmation-envoyee`
- `/adherer/confirmer/:token`
