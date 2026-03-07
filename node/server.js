import crypto from 'node:crypto';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';
import express from 'express';
import { Pool } from 'pg';
import { Resend } from 'resend';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const app = express();
const port = process.env.PORT || 3000;
const appBaseUrl = process.env.APP_BASE_URL || '';
const appSecret = process.env.APP_SECRET || 'change-me';
const mailerFrom = process.env.MAILER_FROM || 'no-reply@union-citoyenne.fr';
const resetSupportersOnBoot = process.env.RESET_SUPPORTERS_ON_BOOT === 'true';

const databaseUrl = process.env.DATABASE_URL;
if (!databaseUrl) {
  throw new Error('DATABASE_URL is required');
}

const pool = new Pool({
  connectionString: databaseUrl,
  ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : false
});

const resendApiKey = getResendApiKey();
const resend = resendApiKey ? new Resend(resendApiKey) : null;
const submitCooldownByIp = new Map();
let supporterTableReady = false;
let supporterResetDone = false;

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));
app.use(express.urlencoded({ extended: false }));
app.use(express.static(path.join(projectRoot, 'public')));

await ensureSupporterTable();

app.get('/', async (req, res) => {
  const confirmedCount = await safeCountConfirmed();
  res.render('home', { confirmedCount });
});

app.get('/charte', async (req, res) => {
  const confirmedCount = await safeCountConfirmed();
  res.render('charter', { confirmedCount });
});

app.get('/adherer', async (req, res) => {
  const confirmedCount = await safeCountConfirmed();
  res.render('adhere', {
    confirmedCount,
    messageType: req.query.type || '',
    message: req.query.message || ''
  });
});

app.post('/adherer', async (req, res) => {
  const { email = '', agreesToCharter, acceptsFutureContact, website = '' } = req.body;

  if (isSpam(req.ip, website)) {
    return res.redirect('/adherer/confirmation-envoyee');
  }

  const normalizedEmail = String(email).trim().toLowerCase();
  const charterAccepted = agreesToCharter === 'on';
  const futureContactAccepted = acceptsFutureContact === 'on';

  if (!isValidEmail(normalizedEmail) || !charterAccepted) {
    return res.redirect('/adherer?type=error&message=Merci+de+verifier+les+champs+du+formulaire.');
  }

  try {
    await ensureSupporterTable();

    const existing = await findSupporterByEmail(normalizedEmail);
    if (existing && existing.is_confirmed) {
      return res.redirect('/adherer?type=info&message=Cette+adresse+email+a+deja+adhere+a+la+charte.');
    }

    let supporterId;
    if (existing) {
      supporterId = existing.id;
      await pool.query(
        `UPDATE supporter
         SET agrees_to_charter = TRUE,
             accepts_future_contact = $1,
             confirmation_sent_at = NOW(),
             ip_hash = $2
         WHERE id = $3`,
        [futureContactAccepted, hashIp(req.ip), supporterId]
      );
    } else {
      const insert = await pool.query(
        `INSERT INTO supporter
          (email, agrees_to_charter, accepts_future_contact, is_confirmed, created_at, confirmation_sent_at, ip_hash)
         VALUES ($1, TRUE, $2, FALSE, NOW(), NOW(), $3)
         RETURNING id`,
        [normalizedEmail, futureContactAccepted, hashIp(req.ip)]
      );
      supporterId = insert.rows[0].id;
    }

    const token = createConfirmationToken({ id: supporterId, email: normalizedEmail });
    const confirmUrl = buildConfirmUrl(req, token);
    await sendConfirmationEmail(normalizedEmail, confirmUrl, futureContactAccepted);

    return res.redirect('/adherer/confirmation-envoyee');
  } catch (error) {
    console.error('Adhesion submit error:', error);
    return res.redirect('/adherer?type=error&message=Le+service+d%27adhesion+est+temporairement+indisponible.');
  }
});

app.get('/adherer/confirmation-envoyee', async (req, res) => {
  const confirmedCount = await safeCountConfirmed();
  res.render('confirmation-sent', { confirmedCount });
});

app.get('/adherer/confirmer/:token', async (req, res) => {
  const token = req.params.token;
  const confirmedCount = await safeCountConfirmed();

  try {
    await ensureSupporterTable();

    const payload = validateConfirmationToken(token);
    const supporter = await pool.query('SELECT * FROM supporter WHERE id = $1', [payload.id]);
    if (!supporter.rows.length) {
      return res.render('confirmed', { confirmedCount, status: 'invalid' });
    }

    const record = supporter.rows[0];
    if (record.email !== payload.email) {
      return res.render('confirmed', { confirmedCount, status: 'invalid' });
    }

    if (record.is_confirmed) {
      return res.render('confirmed', { confirmedCount, status: 'already' });
    }

    await pool.query(
      `UPDATE supporter
       SET is_confirmed = TRUE, confirmed_at = NOW()
       WHERE id = $1`,
      [record.id]
    );

    const newCount = await safeCountConfirmed();
    return res.render('confirmed', { confirmedCount: newCount, status: 'ok' });
  } catch (error) {
    console.error('Confirmation error:', error);
    return res.render('confirmed', { confirmedCount, status: 'invalid' });
  }
});

app.get('/health', async (req, res) => {
  const checks = {
    db: false,
    table: false,
    mailerConfigured: Boolean(resendApiKey && mailerFrom)
  };

  try {
    await pool.query('SELECT 1');
    checks.db = true;
  } catch (error) {
    console.error('Health DB check error:', error);
  }

  try {
    await ensureSupporterTable();
    checks.table = true;
  } catch (error) {
    console.error('Health table check error:', error);
  }

  const ok = checks.db && checks.table;
  return res.status(ok ? 200 : 500).json(checks);
});

app.listen(port, () => {
  console.log(`Union Citoyenne Node app listening on :${port}`);
});

async function ensureSupporterTable() {
  if (supporterTableReady) {
    return;
  }
  await pool.query(`
    CREATE TABLE IF NOT EXISTS supporter (
      id SERIAL PRIMARY KEY,
      email VARCHAR(180) NOT NULL,
      agrees_to_charter BOOLEAN NOT NULL DEFAULT TRUE,
      accepts_future_contact BOOLEAN NOT NULL DEFAULT FALSE,
      is_confirmed BOOLEAN NOT NULL DEFAULT FALSE,
      created_at TIMESTAMP NOT NULL DEFAULT NOW(),
      confirmation_sent_at TIMESTAMP NULL,
      confirmed_at TIMESTAMP NULL,
      ip_hash VARCHAR(64) NULL
    )
  `);
  await pool.query('CREATE UNIQUE INDEX IF NOT EXISTS uniq_supporter_email_lower ON supporter (LOWER(email))');

  if (resetSupportersOnBoot && !supporterResetDone) {
    await pool.query('TRUNCATE TABLE supporter RESTART IDENTITY');
    supporterResetDone = true;
    console.log('Supporter table reset executed (RESET_SUPPORTERS_ON_BOOT=true).');
  }

  supporterTableReady = true;
}

async function safeCountConfirmed() {
  try {
    await ensureSupporterTable();
    const result = await pool.query('SELECT COUNT(*)::int AS total FROM supporter WHERE is_confirmed = TRUE');
    return result.rows[0]?.total ?? 0;
  } catch (error) {
    console.error('Count confirmed error:', error);
    return 0;
  }
}

async function findSupporterByEmail(email) {
  const result = await pool.query('SELECT * FROM supporter WHERE LOWER(email) = $1 LIMIT 1', [email]);
  return result.rows[0] || null;
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function hashIp(ip) {
  if (!ip) {
    return null;
  }
  return crypto.createHash('sha256').update(ip).digest('hex');
}

function isSpam(ip, honeypotValue) {
  if (String(honeypotValue || '').trim() !== '') {
    return true;
  }
  const now = Date.now();
  const last = submitCooldownByIp.get(ip) || 0;
  submitCooldownByIp.set(ip, now);
  return now - last < 5000;
}

function createConfirmationToken(payload) {
  const data = {
    ...payload,
    exp: Date.now() + 24 * 60 * 60 * 1000
  };
  const raw = Buffer.from(JSON.stringify(data)).toString('base64url');
  const signature = crypto.createHmac('sha256', appSecret).update(raw).digest('base64url');
  return `${raw}.${signature}`;
}

function validateConfirmationToken(token) {
  const [raw, signature] = String(token).split('.');
  if (!raw || !signature) {
    throw new Error('Invalid token format');
  }
  const expected = crypto.createHmac('sha256', appSecret).update(raw).digest('base64url');
  if (signature !== expected) {
    throw new Error('Invalid token signature');
  }
  const payload = JSON.parse(Buffer.from(raw, 'base64url').toString('utf-8'));
  if (!payload.exp || Date.now() > payload.exp) {
    throw new Error('Token expired');
  }
  return payload;
}

async function sendConfirmationEmail(to, confirmUrl, acceptsFutureContact) {
  if (!resend) {
    throw new Error('Resend API key is missing. Set RESEND_API_KEY or MAILER_DSN.');
  }
  const futureContactHtml = acceptsFutureContact
    ? '<p>Vous avez indique vouloir etre tenu informe. Nous vous contacterons lorsqu il y aura du neuf sur le projet.</p>'
    : '';
  const futureContactText = acceptsFutureContact
    ? '\nVous avez indique vouloir etre tenu informe. Nous vous contacterons lorsqu il y aura du neuf sur le projet.\n'
    : '';

  await resend.emails.send({
    from: mailerFrom,
    to,
    subject: 'Merci pour votre adhesion a Union Citoyenne',
    text:
      `Merci pour votre adhesion a la charte d Union Citoyenne.\n\n` +
      `Pour finaliser votre adhesion, confirmez votre adresse email ici:\n${confirmUrl}\n` +
      `${futureContactText}` +
      `\nSi vous ne trouvez pas ce message, verifiez aussi votre dossier spam/indesirable.\n`,
    html: `
      <h1>Union Citoyenne</h1>
      <p>Merci pour votre adhesion a la charte.</p>
      <p>Pour finaliser votre adhesion, merci de confirmer votre adresse email en cliquant sur ce lien:</p>
      <p><a href="${confirmUrl}">Confirmer mon adhesion</a></p>
      <p>Ce lien expire dans 24 heures.</p>
      ${futureContactHtml}
      <p style="color:#555;">Si vous ne trouvez pas ce message, verifiez aussi votre dossier spam/indesirable.</p>
    `
  });
}

function buildConfirmUrl(req, token) {
  const base = appBaseUrl || getRequestBaseUrl(req);
  return `${base.replace(/\/$/, '')}/adherer/confirmer/${token}`;
}

function getRequestBaseUrl(req) {
  const forwardedProto = req.headers['x-forwarded-proto'];
  const forwardedHost = req.headers['x-forwarded-host'];
  const host = (forwardedHost || req.headers.host || `localhost:${port}`).toString();
  const proto = (forwardedProto || req.protocol || 'http').toString().split(',')[0].trim();
  return `${proto}://${host}`;
}

function getResendApiKey() {
  if (process.env.RESEND_API_KEY) {
    return process.env.RESEND_API_KEY;
  }
  const dsn = process.env.MAILER_DSN || '';
  const match = dsn.match(/^resend\+api:\/\/([^@]+)@/);
  return match ? decodeURIComponent(match[1]) : '';
}
