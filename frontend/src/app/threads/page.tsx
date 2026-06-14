'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AppNav } from '@/components/AppNav';
import { gmail, getToken, threads } from '@/lib/api';

function labelBadge(label?: string) {
  if (!label) return null;
  return <span className={`badge badge-${label}`}>{label.replace('_', ' ')}</span>;
}

export default function ThreadsPage() {
  const router = useRouter();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (!getToken()) router.push('/login');
  }, [router]);

  useEffect(() => {
    if (!getToken()) return;

    let cancelled = false;
    let syncing = false;

    async function tick() {
      if (syncing) return;
      syncing = true;
      try {
        await gmail.syncAll();
        if (cancelled) return;
        queryClient.invalidateQueries({ queryKey: ['threads'] });
        queryClient.invalidateQueries({ queryKey: ['gmail-accounts'] });
      } catch {
        // ignore transient sync errors
      } finally {
        syncing = false;
      }
    }

    tick();
    const id = setInterval(tick, 30_000);
    return () => {
      cancelled = true;
      clearInterval(id);
    };
  }, [queryClient]);

  const { data, isLoading } = useQuery({
    queryKey: ['threads'],
    queryFn: () => threads.list(),
    refetchInterval: 10_000,
  });

  return (
    <>
      <AppNav />
      <div className="container">
        <h1 style={{ marginBottom: '1.5rem' }}>Email threads</h1>

        {isLoading && <p style={{ color: 'var(--muted)' }}>Loading…</p>}

        {data?.data?.map((thread) => {
          const latestMessage = thread.messages?.[thread.messages.length - 1];
          const classification = latestMessage?.classification;
          const draft = latestMessage?.draft_reply;

          return (
            <Link key={thread.id} href={`/threads/${thread.id}`} className="card" style={{ display: 'block' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: '1rem' }}>
                <div>
                  <strong>{thread.subject || '(no subject)'}</strong>
                  <p style={{ color: 'var(--muted)', fontSize: '0.9rem', marginTop: '0.35rem' }}>
                    {thread.snippet}
                  </p>
                  <p style={{ color: 'var(--muted)', fontSize: '0.8rem', marginTop: '0.5rem' }}>
                    {thread.last_message_at ? new Date(thread.last_message_at).toLocaleString() : ''}
                  </p>
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', alignItems: 'flex-end' }}>
                  {labelBadge(classification?.label)}
                  {draft && <span className={`badge badge-${draft.status}`}>{draft.status.replace('_', ' ')}</span>}
                </div>
              </div>
            </Link>
          );
        })}

        {!isLoading && !data?.data?.length && (
          <p style={{ color: 'var(--muted)' }}>No threads yet. Connect Gmail and send a test email to your inbox.</p>
        )}
      </div>
    </>
  );
}
