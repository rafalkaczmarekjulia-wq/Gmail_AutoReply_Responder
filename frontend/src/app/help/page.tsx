'use client';

import Link from 'next/link';
import { AppNav } from '@/components/AppNav';

export default function HelpPage() {
  return (
    <>
      <AppNav />
      <div className="container">
        <h1 style={{ marginBottom: '1.5rem' }}>Help — Gmail connect &amp; mailboxes</h1>

        <div className="card help-card">
          <h2>For users (after you register)</h2>
          <ol className="help-steps">
            <li>
              <Link href="/register">Register</Link> or <Link href="/login">sign in</Link> to this app (your dashboard
              account — not your Google password stored here for Gmail).
            </li>
            <li>
              Go to <Link href="/dashboard/mailboxes">Mailboxes</Link>.
            </li>
            <li>
              Click <strong>Connect Gmail</strong> → Google sign-in → choose the Gmail inbox to link.
            </li>
            <li>Repeat Connect Gmail for each additional mailbox you own.</li>
            <li>Send a test email to that inbox, click <strong>Sync now</strong>, then check <Link href="/threads">Threads</Link>.</li>
          </ol>
        </div>

        <div className="card help-card">
          <h2>Multiple users on the same app</h2>
          <p style={{ color: 'var(--muted)', marginBottom: '1rem' }}>
            User A and User B each register separately. Each connects their own Gmail accounts. Mailboxes are isolated by
            app login — User A never sees User B&apos;s mail.
          </p>
          <pre className="code-block">
{`User A (alice@mycompany.com)
  └── Connect Gmail → alice.personal@gmail.com
  └── Connect Gmail → alice.work@gmail.com

User B (bob@other.com)
  └── Connect Gmail → bob@gmail.com`}
          </pre>
        </div>

        <div className="card help-card">
          <h2>MVP = product v1 (same app when published)</h2>
          <p style={{ color: 'var(--muted)', marginBottom: '1rem' }}>
            This dashboard is version 1 of the product — not a separate &quot;demo frontend.&quot; When you launch,
            customers use the same <strong>Connect Gmail</strong> flow.
          </p>
        </div>

        <div className="card help-card">
          <h2>Google OAuth: Testing vs Published (not the same as MVP)</h2>
          <p style={{ color: 'var(--muted)', marginBottom: '1rem' }}>
            <strong>Published production:</strong> OAuth consent screen must be <strong>In production</strong>{' '}
            (Published) after Google verification. <strong>Do not</strong> use Testing mode for real customers — they
            would be blocked unless on a test-user list.
          </p>
          <p style={{ color: 'var(--muted)', marginBottom: '1rem' }}>
            <strong>Local development only:</strong> keep Google app in <em>Testing</em> mode and add demo accounts
            under <strong>OAuth consent screen → Test users</strong>. That is a Google sandbox rule for localhost — not
            how the live product works.
          </p>
          <p style={{ color: 'var(--muted)', fontSize: '0.9rem' }}>
            Details: <code>docs/GOOGLE_OAUTH_DEV_VS_PRODUCTION.md</code>
          </p>
        </div>

        <div className="card help-card">
          <h2>For the developer (once per platform)</h2>
          <p style={{ color: 'var(--muted)', marginBottom: '1rem' }}>
            Create <strong>one</strong> Google Cloud OAuth client. Put Client ID and Secret in{' '}
            <code>backend/.env</code>. Users never type these values.
          </p>
          <pre className="code-block">
{`GOOGLE_CLIENT_ID=....apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-...
GOOGLE_REDIRECT_URI=http://localhost:8000/api/gmail/callback`}
          </pre>
          <p style={{ color: 'var(--muted)', marginTop: '1rem' }}>
            Full setup: <code>docs/GOOGLE_SETUP.md</code> in the project folder.
          </p>
        </div>
      </div>
    </>
  );
}
