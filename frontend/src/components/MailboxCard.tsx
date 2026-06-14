'use client';

import { GmailAccount } from '@/lib/api';

type Props = {
  account: GmailAccount;
  onSync: (account: GmailAccount) => void;
  onDisconnect: (account: GmailAccount) => void;
  syncing?: boolean;
};

function statusClass(status: string) {
  if (status === 'active') return 'badge-active';
  if (status === 'token_revoked' || status === 'error') return 'badge-error';
  return 'badge-warning';
}

export function MailboxCard({ account, onSync, onDisconnect, syncing }: Props) {
  return (
    <div className="mailbox-card">
      <div className="mailbox-card-main">
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flexWrap: 'wrap' }}>
          <strong>{account.gmail_email}</strong>
          <span className={`badge ${statusClass(account.status)}`}>
            {account.status_label || account.status}
          </span>
        </div>
        <div className="mailbox-meta">
          Connected {account.connected_at ? new Date(account.connected_at).toLocaleString() : '—'}
          {account.messages_count !== undefined && ` · ${account.messages_count} messages synced`}
          {account.watch_expires_at
            ? ` · Watch until ${new Date(account.watch_expires_at).toLocaleString()}`
            : ' · Manual sync mode'}
        </div>
      </div>
      <div className="mailbox-actions">
        <button onClick={() => onSync(account)} className="btn btn-secondary" disabled={syncing}>
          {syncing ? 'Syncing…' : 'Sync now'}
        </button>
        <button onClick={() => onDisconnect(account)} className="btn btn-danger">
          Disconnect
        </button>
      </div>
    </div>
  );
}
