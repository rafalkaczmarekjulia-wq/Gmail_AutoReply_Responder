'use client';

import { Suspense, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AppNav } from '@/components/AppNav';
import { auth, getToken, gmail, settings } from '@/lib/api';
export default function DashboardPage() {
  return (
    <Suspense fallback={<div className="container"><p>Loading…</p></div>}>
      <DashboardContent />
    </Suspense>
  );
}

function DashboardContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const connected = searchParams.get('connected');
  const connectedEmail = searchParams.get('email');
  const tab = searchParams.get('tab');
  const [replyPrompt, setReplyPrompt] = useState('');
  const [settingsSaved, setSettingsSaved] = useState(false);
  const [settingsError, setSettingsError] = useState('');
  useEffect(() => {
    if (!getToken()) router.push('/login');
  }, [router]);

  const { data: user } = useQuery({ queryKey: ['me'], queryFn: auth.me, retry: false });
  const { data: accountsData } = useQuery({ queryKey: ['gmail-accounts'], queryFn: gmail.accounts });
  const { data: googleStatus } = useQuery({ queryKey: ['gmail-status'], queryFn: gmail.status });
  const { data: settingsData, isLoading: settingsLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: () => settings.get(),
    enabled: tab === 'settings',
  });

  useEffect(() => {
    if (settingsData?.reply_prompt) {
      setReplyPrompt(settingsData.reply_prompt);
    }
  }, [settingsData?.reply_prompt]);

  async function handleSaveSettings(e: React.FormEvent) {
    e.preventDefault();
    setSettingsError('');
    setSettingsSaved(false);
    try {
      await settings.updateReplyPrompt(replyPrompt);
      queryClient.invalidateQueries({ queryKey: ['settings'] });
      setSettingsSaved(true);
    } catch (err) {
      setSettingsError(err instanceof Error ? err.message : 'Save failed');
    }
  }

  const mailboxCount = accountsData?.data?.length ?? 0;

  if (tab === 'settings') {
    return (
      <>
        <AppNav />
        <div className="container" style={{ maxWidth: 720 }}>
          <Link href="/dashboard" style={{ color: 'var(--muted)', fontSize: '0.9rem' }}>
            &larr; Back to dashboard
          </Link>
          <h1 style={{ margin: '1rem 0 0.5rem' }}>Reply prompt settings</h1>
          <p style={{ color: 'var(--muted)', marginBottom: '1.5rem' }}>
            Customize how the AI writes reply drafts for your incoming emails.
          </p>
          {settingsLoading ? (
            <p style={{ color: 'var(--muted)' }}>Loading...</p>
          ) : (
            <form onSubmit={handleSaveSettings} className="card">
              <p style={{ color: 'var(--muted)', fontSize: '0.9rem', marginBottom: '1rem' }}>
                This prompt is sent to the LLM for every draft. Include tone, what to mention, and how to sign off.
                {settingsData?.llm_driver && (
                  <>
                    {' '}
                    Current model: <strong>{settingsData.llm_model}</strong> ({settingsData.llm_driver} driver).
                  </>
                )}
              </p>
              <textarea
                rows={12}
                value={replyPrompt}
                onChange={(e) => setReplyPrompt(e.target.value)}
                placeholder="Describe how replies should be written..."
                required
                minLength={20}
              />
              {settingsError && <p className="error">{settingsError}</p>}
              {settingsSaved && <p style={{ color: 'var(--success)' }}>Reply prompt saved.</p>}
              <button type="submit" className="btn btn-primary" style={{ marginTop: '1rem' }}>
                Save prompt
              </button>
            </form>
          )}
        </div>
      </>
    );
  }
  return (
    <>
      <AppNav />
      <div className="container">
        <h1 style={{ marginBottom: '0.35rem' }}>Dashboard</h1>
        {user && <p style={{ color: 'var(--muted)', marginBottom: '1.5rem' }}>Signed in as {user.email}</p>}

        {connected && (
          <p style={{ color: 'var(--success)', marginBottom: '1rem' }}>
            Gmail connected{connectedEmail ? `: ${connectedEmail}` : ''}.{' '}
            <Link href="/dashboard/mailboxes">Manage mailboxes</Link>
          </p>
        )}

        <div className="dashboard-grid">
          <Link href="/dashboard/mailboxes" className="card dashboard-tile">
            <h2>Mailboxes</h2>
            <p className="tile-stat">{mailboxCount}</p>
            <p style={{ color: 'var(--muted)', fontSize: '0.9rem' }}>Connect Gmail accounts</p>
          </Link>
          <Link href="/threads" className="card dashboard-tile">
            <h2>Threads</h2>
            <p style={{ color: 'var(--muted)', fontSize: '0.9rem' }}>Classifications &amp; drafts</p>
          </Link>
          <Link href="/dashboard?tab=settings" className="card dashboard-tile">
            <h2>Settings</h2>
            <p style={{ color: 'var(--muted)', fontSize: '0.9rem' }}>Reply message prompt</p>
          </Link>
          <Link href="/help" className="card dashboard-tile">            <h2>Help</h2>
            <p style={{ color: 'var(--muted)', fontSize: '0.9rem' }}>How connect works</p>
          </Link>
        </div>

        {!googleStatus?.oauth_configured && (
          <div className="card" style={{ marginTop: '1rem', borderColor: 'var(--warning)' }}>
            <h2>Developer: enable Gmail connect</h2>
            <p style={{ color: 'var(--muted)' }}>
              Set <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> in <code>backend/.env</code> once.
              See <Link href="/help">Help</Link>.
            </p>
          </div>
        )}

        {mailboxCount === 0 && googleStatus?.oauth_configured && (
          <div className="card" style={{ marginTop: '1rem' }}>
            <h2>Get started</h2>
            <p style={{ color: 'var(--muted)', marginBottom: '1rem' }}>
              Connect at least one Gmail mailbox to run the auto-responder workflow.
            </p>
            <Link href="/dashboard/mailboxes" className="btn btn-primary">
              Connect your first Gmail
            </Link>
          </div>
        )}
      </div>
    </>
  );
}
