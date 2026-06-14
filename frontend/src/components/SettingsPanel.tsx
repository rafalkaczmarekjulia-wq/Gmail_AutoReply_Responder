'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AppNav } from '@/components/AppNav';
import { getToken, settings } from '@/lib/api';

export default function SettingsPanel() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [replyPrompt, setReplyPrompt] = useState('');
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!getToken()) router.push('/login');
  }, [router]);

  const { data, isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: () => settings.get(),
  });

  useEffect(() => {
    if (data?.reply_prompt) {
      setReplyPrompt(data.reply_prompt);
    }
  }, [data?.reply_prompt]);

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    setSaved(false);
    try {
      await settings.updateReplyPrompt(replyPrompt);
      queryClient.invalidateQueries({ queryKey: ['settings'] });
      setSaved(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Save failed');
    }
  }

  return (
    <>
      <AppNav />
      <div className="container" style={{ maxWidth: 720 }}>
        <h1 style={{ marginBottom: '0.5rem' }}>Settings</h1>
        <p style={{ color: 'var(--muted)', marginBottom: '1.5rem' }}>
          Customize how the AI writes reply drafts for your incoming emails.
        </p>

        {isLoading ? (
          <p style={{ color: 'var(--muted)' }}>Loading...</p>
        ) : (
          <form onSubmit={handleSave} className="card">
            <h2 style={{ marginBottom: '0.75rem' }}>Reply message prompt</h2>
            <p style={{ color: 'var(--muted)', fontSize: '0.9rem', marginBottom: '1rem' }}>
              This prompt is sent to the LLM for every draft. Include tone, what to mention, and how to sign off.
              {data?.llm_driver && (
                <>
                  {' '}
                  Current model: <strong>{data.llm_model}</strong> ({data.llm_driver} driver).
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
            {error && <p className="error">{error}</p>}
            {saved && <p style={{ color: 'var(--success)' }}>Reply prompt saved.</p>}
            <button type="submit" className="btn btn-primary" style={{ marginTop: '1rem' }}>
              Save prompt
            </button>
          </form>
        )}
      </div>
    </>
  );
}
