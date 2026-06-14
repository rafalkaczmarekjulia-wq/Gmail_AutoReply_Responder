'use client';

import { useEffect, useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AppNav } from '@/components/AppNav';
import { drafts, getToken, gmail, threads } from '@/lib/api';

export default function ThreadDetailPage() {
  const router = useRouter();
  const params = useParams();
  const threadId = Number(params.id);
  const queryClient = useQueryClient();
  const [draftBody, setDraftBody] = useState('');
  const [actionError, setActionError] = useState('');

  useEffect(() => {
    if (!getToken()) router.push('/login');
  }, [router]);

  const latestMessage = (data: Awaited<ReturnType<typeof threads.show>> | undefined) =>
    data?.messages?.[data.messages.length - 1];

  const needsProcessing = (data: Awaited<ReturnType<typeof threads.show>> | undefined) => {
    const msg = latestMessage(data);
    if (!msg) return false;
    const label = msg.classification?.label;
    if (label === 'not_interested') return false;
    return !msg.draft_reply;
  };

  const { data: thread, isLoading } = useQuery({
    queryKey: ['thread', threadId],
    queryFn: () => threads.show(threadId),
    enabled: !!threadId,
    refetchInterval: (query) => (needsProcessing(query.state.data) ? 3000 : false),
  });

  const message = thread?.messages?.[thread.messages.length - 1];
  const draft = message?.draft_reply;
  const classification = message?.classification;
  const waitingForDraft =
    message && !draft && classification?.label !== 'not_interested';

  useEffect(() => {
    if (draft?.body) setDraftBody(draft.body);
  }, [draft?.body]);

  useEffect(() => {
    if (!getToken()) return;
    gmail.syncAll().catch(() => undefined);
  }, [threadId]);

  async function handleApprove() {
    if (!draft) return;
    setActionError('');
    try {
      await drafts.approve(draft.id, draftBody);
      queryClient.invalidateQueries({ queryKey: ['thread', threadId] });
      queryClient.invalidateQueries({ queryKey: ['threads'] });
    } catch (err) {
      setActionError(err instanceof Error ? err.message : 'Approve failed');
    }
  }

  async function handleReject() {
    if (!draft) return;
    setActionError('');
    try {
      await drafts.reject(draft.id);
      queryClient.invalidateQueries({ queryKey: ['thread', threadId] });
      queryClient.invalidateQueries({ queryKey: ['threads'] });
    } catch (err) {
      setActionError(err instanceof Error ? err.message : 'Reject failed');
    }
  }

  if (isLoading) {
    return (
      <div className="container">
        <p style={{ color: 'var(--muted)' }}>Loading...</p>
      </div>
    );
  }

  if (!thread) {
    return (
      <div className="container">
        <p>Thread not found.</p>
        <Link href="/threads">Back to threads</Link>
      </div>
    );
  }

  return (
    <>
      <AppNav />
      <div className="container">
        <Link href="/threads" style={{ color: 'var(--muted)', fontSize: '0.9rem' }}>
          &larr; Back to threads
        </Link>
        <h1 style={{ margin: '1rem 0 0.5rem' }}>{thread.subject || '(no subject)'}</h1>

        {message && (
          <>
            <div className="card">
              <p style={{ color: 'var(--muted)', marginBottom: '0.5rem' }}>
                From: {message.from_email} &middot; {new Date(message.received_at).toLocaleString()}
              </p>
              {classification ? (
                <>
                  <p style={{ marginBottom: '0.5rem' }}>
                    Classification:{' '}
                    <span className={`badge badge-${classification.label}`}>
                      {classification.label.replace('_', ' ')}
                    </span>{' '}
                    ({Math.round(classification.confidence * 100)}% confidence)
                    {classification.model && (
                      <span style={{ color: 'var(--muted)', fontSize: '0.85rem' }}>
                        {' '}
                        &middot; model: {classification.model}
                      </span>
                    )}
                  </p>
                  {classification.extracted_keywords && classification.extracted_keywords.length > 0 && (
                    <p style={{ marginBottom: '1rem' }}>
                      Keywords:{' '}
                      {classification.extracted_keywords.map((kw) => (
                        <span key={kw} className="badge" style={{ marginRight: '0.35rem' }}>
                          {kw}
                        </span>
                      ))}
                    </p>
                  )}
                </>
              ) : waitingForDraft ? (
                <p style={{ color: 'var(--muted)', marginBottom: '1rem' }}>
                  Analyzing email and preparing draft...
                </p>
              ) : null}
              <pre style={{ whiteSpace: 'pre-wrap', fontFamily: 'inherit' }}>{message.body_text}</pre>
            </div>

            {draft ? (
              <div className="card">
                <h2 style={{ marginBottom: '1rem' }}>Draft reply</h2>
                <span className={`badge badge-${draft.status}`} style={{ marginBottom: '1rem' }}>
                  {draft.status.replace('_', ' ')}
                </span>
                {actionError && <p className="error">{actionError}</p>}
                <textarea
                  rows={8}
                  value={draftBody}
                  onChange={(e) => setDraftBody(e.target.value)}
                  disabled={draft.status !== 'pending_approval'}
                />
                {draft.status === 'pending_approval' && (
                  <div style={{ display: 'flex', gap: '0.75rem', marginTop: '0.5rem' }}>
                    <button onClick={handleApprove} className="btn btn-primary">
                      Approve &amp; send
                    </button>
                    <button onClick={handleReject} className="btn btn-danger">
                      Reject
                    </button>
                  </div>
                )}
                {draft.status === 'sent' && (
                  <p style={{ color: 'var(--success)', marginTop: '0.75rem' }}>
                    Reply sent via Gmail.
                  </p>
                )}
              </div>
            ) : waitingForDraft ? (
              <div className="card">
                <h2 style={{ marginBottom: '0.75rem' }}>Draft reply</h2>
                <p style={{ color: 'var(--muted)' }}>
                  Preparing your draft automatically. This usually takes a few seconds...
                </p>
              </div>
            ) : classification?.label === 'not_interested' ? (
              <div className="card">
                <p style={{ color: 'var(--muted)' }}>
                  No draft — message classified as not interested.
                </p>
              </div>
            ) : null}
          </>
        )}
      </div>
    </>
  );
}
