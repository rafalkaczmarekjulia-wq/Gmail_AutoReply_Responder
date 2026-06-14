'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { auth, setToken } from '@/lib/api';

export default function RegisterPage() {
  const router = useRouter();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  
  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    try {
      const res = await auth.register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      setToken(res.token);
      router.push('/dashboard');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Registration failed');
    }
  }

  return (
    <div className="container" style={{ maxWidth: 420 }}>
      <h1 style={{ marginBottom: '1.5rem' }}>Create account</h1>
      {error && <p className="error">{error}</p>}
      <form onSubmit={handleSubmit} className="card">
        <label>Name</label>
        <input value={name} onChange={(e) => setName(e.target.value)} required />
        <label>Email</label>
        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        <label>Password</label>
        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        <label>Confirm password</label>
        <input
          type="password"
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          required
        />
        <button type="submit" className="btn btn-primary" style={{ width: '100%' }}>
          Register
        </button>
      </form>
      <p style={{ marginTop: '1rem', color: 'var(--muted)' }}>
        Already have an account? <Link href="/login">Sign in</Link>
      </p>
    </div>
  );
}
