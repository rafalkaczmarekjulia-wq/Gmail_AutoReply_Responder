type Props = {
  oauthConfigured: boolean;
};

export function ConnectGmailHelp({ oauthConfigured }: Props) {
  if (!oauthConfigured) {
    return null;
  }

  return (
    <div className="card help-card">
      <h2>How to connect a Gmail mailbox</h2>
      <ol className="help-steps">
        <li>
          Click <strong>Connect Gmail</strong> below.
        </li>
        <li>Sign in with Google (use the Google account that owns the mailbox).</li>
        <li>Approve access for this app (read inbox + create drafts).</li>
        <li>You return here — the mailbox appears in your list.</li>
        <li>
          To connect <strong>another</strong> Gmail, click <strong>Connect Gmail</strong> again and pick a different
          Google account.
        </li>
      </ol>
      <p className="help-note">
        You never enter Client ID or Secret. The platform uses one Google OAuth app; your mailbox is linked to{' '}
        <strong>your</strong> dashboard account only. Other users on this app connect their own mailboxes the same way.
      </p>
    </div>
  );
}
