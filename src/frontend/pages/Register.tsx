import React, { useState } from 'react';
import { useAuth } from '../hooks/useAuth';

export default function Register({ onSwitch }: { onSwitch: () => void }) {
  const { register } = useAuth();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(''); setLoading(true);
    try { await register(name, email, password); }
    catch (err: any) { setError(err.message); }
    finally { setLoading(false); }
  };

  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">Solen</h1>
          <p className="text-slate-400 mt-2">Start your wellness journey — free</p>
        </div>

        <form onSubmit={submit} className="bg-slate-900 border border-slate-800 rounded-2xl p-8 space-y-5 shadow-2xl">
          {error && (
            <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-3">{error}</div>
          )}

          {[
            { label: 'Your name', value: name, set: setName, type: 'text', placeholder: 'Alex' },
            { label: 'Email', value: email, set: setEmail, type: 'email', placeholder: 'you@example.com' },
            { label: 'Password', value: password, set: setPassword, type: 'password', placeholder: '8+ characters' },
          ].map(f => (
            <div key={f.label}>
              <label className="block text-sm text-slate-400 mb-1.5">{f.label}</label>
              <input
                type={f.type} required value={f.value} placeholder={f.placeholder}
                onChange={e => f.set(e.target.value)}
                className="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 transition"
              />
            </div>
          ))}

          <button
            type="submit" disabled={loading}
            className="w-full bg-gradient-to-r from-blue-600 to-violet-600 hover:from-blue-500 hover:to-violet-500 text-white font-semibold py-3 rounded-xl transition-all duration-200 disabled:opacity-50 shadow-lg shadow-blue-500/20"
          >
            {loading ? 'Creating account…' : 'Create Free Account'}
          </button>

          <p className="text-center text-sm text-slate-500">
            Have an account?{' '}
            <button type="button" onClick={onSwitch} className="text-blue-400 hover:text-blue-300 transition">
              Sign in →
            </button>
          </p>
        </form>
      </div>
    </div>
  );
}
