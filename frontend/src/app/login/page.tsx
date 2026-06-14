'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { auth, setToken } from '@/lib/api';

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    try {
      const res = await auth.login({ email, password });
      setToken(res.token);
      router.push('/dashboard');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    }
  }

  return (
    <div className="container" style={{ maxWidth: 420 }}>
      <h1 style={{ marginBottom: '1.5rem' }}>Sign in</h1>
      {error && <p className="error">{error}</p>}
      <form onSubmit={handleSubmit} className="card">
        <label>Email</label>
        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        <label>Password</label>
        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        <button type="submit" className="btn btn-primary" style={{ width: '100%' }}>
          Sign in
        </button>
      </form>
      <p style={{ marginTop: '1rem', color: 'var(--muted)' }}>
        No account? <Link href="/register">Register</Link>
      </p>
    </div>
  );
}
