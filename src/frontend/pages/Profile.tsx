import React, { useEffect, useState } from 'react';
import { useAuth } from '../hooks/useAuth';

export default function Profile({ onLogout }: { onLogout: () => void }) {
  const { user } = useAuth();
  const [profile, setProfile] = useState<any>(null);
  const [form, setForm] = useState({ name: '', coachName: '', purpose: '', tone: 'gentle', challenge: '' });
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    fetch('/api/profile', { credentials: 'include' })
      .then(r => r.json()).then(data => {
        setProfile(data.profile);
        setForm({
          name: data.user?.name ?? '',
          coachName: data.profile?.coach_name ?? data.profile?.coachName ?? '',
          purpose: data.profile?.purpose ?? '',
          tone: data.profile?.tone ?? 'gentle',
          challenge: data.profile?.challenge ?? '',
        });
      });
  }, []);

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    await fetch('/api/profile', {
      method: 'PUT', credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form),
    });
    setSaving(false);
    setSaved(true);
    setTimeout(() => setSaved(false), 2000);
  };

  return (
    <div className="min-h-full bg-slate-950 p-4 md:p-8">
      <div className="max-w-xl mx-auto">
        {/* Avatar */}
        <div className="flex items-center gap-4 mb-8">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-violet-500 flex items-center justify-center text-2xl font-bold text-white">
            {user?.name?.charAt(0).toUpperCase()}
          </div>
          <div>
            <h2 className="text-xl font-bold text-white">{user?.name}</h2>
            <p className="text-slate-400 text-sm">{user?.email}</p>
            <span className="text-xs font-semibold capitalize bg-blue-600/20 text-blue-300 border border-blue-500/20 px-2 py-0.5 rounded-full mt-1 inline-block">
              {user?.plan} plan
            </span>
          </div>
        </div>

        <form onSubmit={save} className="space-y-5">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
            <h3 className="text-white font-semibold mb-1">Personal Info</h3>

            {[
              { label: 'Your Name', key: 'name', placeholder: 'Alex' },
              { label: 'Coach Name', key: 'coachName', placeholder: 'What should your coach call you?' },
              { label: 'Main Challenge', key: 'challenge', placeholder: 'e.g. anxiety, sleep, recovery…' },
              { label: 'Wellness Purpose', key: 'purpose', placeholder: 'Why are you here? What do you want to achieve?' },
            ].map(f => (
              <div key={f.key}>
                <label className="block text-sm text-slate-400 mb-1.5">{f.label}</label>
                <input type="text" value={(form as any)[f.key]} placeholder={f.placeholder}
                  onChange={e => setForm(prev => ({ ...prev, [f.key]: e.target.value }))}
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 transition text-sm" />
              </div>
            ))}

            <div>
              <label className="block text-sm text-slate-400 mb-1.5">Coach Tone</label>
              <select value={form.tone} onChange={e => setForm(prev => ({ ...prev, tone: e.target.value }))}
                className="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-blue-500 transition text-sm">
                <option value="gentle">Gentle & Warm</option>
                <option value="direct">Direct & Structured</option>
                <option value="motivational">Motivational</option>
                <option value="clinical">Clinical & Evidence-Based</option>
              </select>
            </div>
          </div>

          <button type="submit" disabled={saving}
            className={`w-full py-3 rounded-xl font-semibold text-white transition-all ${saved ? 'bg-emerald-600' : 'bg-gradient-to-r from-blue-600 to-violet-600 hover:from-blue-500 hover:to-violet-500'} disabled:opacity-50`}>
            {saving ? 'Saving…' : saved ? '✓ Saved!' : 'Save Changes'}
          </button>
        </form>

        {/* Sign Out */}
        <button onClick={onLogout}
          className="w-full mt-4 text-slate-500 hover:text-red-400 text-sm py-3 transition border border-slate-800 rounded-xl hover:border-red-900">
          Sign Out
        </button>
      </div>
    </div>
  );
}
