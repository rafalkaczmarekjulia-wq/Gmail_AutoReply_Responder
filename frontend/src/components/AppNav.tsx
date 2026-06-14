'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { auth, clearToken } from '@/lib/api';

export function AppNav() {
  const router = useRouter();

  async function handleLogout() {
    try {
      await auth.logout();
    } finally {
      clearToken();
      router.push('/login');
    }
  }

  return (
    <nav className="nav">
      <Link href="/dashboard">Dashboard</Link>
      <Link href="/dashboard/mailboxes">Mailboxes</Link>
      <Link href="/threads">Threads</Link>
      <Link href="/dashboard?tab=settings">Settings</Link>
      <Link href="/help">Help</Link>
      <button onClick={handleLogout} className="btn btn-secondary" style={{ marginLeft: 'auto' }}>
        Logout
      </button>
    </nav>
  );
}
