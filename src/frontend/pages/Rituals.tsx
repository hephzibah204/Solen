import React, { useEffect, useState } from 'react';

interface Ritual { id: string; title: string; description: string; icon: string; duration: string; completed: boolean; }

const PERIODS = ['morning', 'afternoon', 'evening'] as const;
type Period = typeof PERIODS[number];

const PERIOD_META: Record<Period, { label: string; icon: string; gradient: string }> = {
  morning:   { label: 'Morning', icon: '🌅', gradient: 'from-amber-500 to-orange-500' },
  afternoon: { label: 'Afternoon', icon: '☀️', gradient: 'from-yellow-500 to-amber-500' },
  evening:   { label: 'Evening', icon: '🌙', gradient: 'from-indigo-500 to-violet-500' },
};

export default function Rituals() {
  const [period, setPeriod] = useState<Period>('morning');
  const [rituals, setRituals] = useState<Ritual[]>([]);
  const [loading, setLoading] = useState(true);
  const [completing, setCompleting] = useState<string | null>(null);
  const [allDone, setAllDone] = useState(false);

  const load = async (p: Period) => {
    setLoading(true);
    const r = await fetch(`/api/rituals?period=${p}`, { credentials: 'include' });
    const data = await r.json();
    setRituals(data.rituals ?? []);
    setAllDone((data.rituals ?? []).every((r: Ritual) => r.completed));
    setLoading(false);
  };

  useEffect(() => { load(period); }, [period]);

  const complete = async (ritualId: string) => {
    setCompleting(ritualId);
    await fetch('/api/rituals/complete', {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ritualId, period }),
    });
    const updated = rituals.map(r => r.id === ritualId ? { ...r, completed: true } : r);
    setRituals(updated);
    setAllDone(updated.every(r => r.completed));
    setCompleting(null);
  };

  const meta = PERIOD_META[period];

  return (
    <div className="min-h-full bg-slate-950 p-4 md:p-8">
      <div className="max-w-2xl mx-auto">
        <h2 className="text-2xl font-bold text-white mb-2">Daily Rituals</h2>
        <p className="text-slate-400 mb-6">Build consistency, one small step at a time</p>

        {/* Period Tabs */}
        <div className="flex gap-2 mb-6 bg-slate-900 p-1 rounded-xl">
          {PERIODS.map(p => (
            <button key={p} onClick={() => setPeriod(p)}
              className={`flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-lg text-sm font-medium transition-all
                ${period === p ? `bg-gradient-to-r ${PERIOD_META[p].gradient} text-white shadow` : 'text-slate-400 hover:text-white'}`}>
              <span>{PERIOD_META[p].icon}</span>
              <span className="hidden sm:inline">{PERIOD_META[p].label}</span>
            </button>
          ))}
        </div>

        {/* All Done Banner */}
        {allDone && !loading && (
          <div className="mb-6 bg-gradient-to-r from-emerald-900/50 to-teal-900/50 border border-emerald-700/30 rounded-2xl p-4 text-center animate-pulse">
            <p className="text-2xl mb-1">🎉</p>
            <p className="text-emerald-300 font-semibold">All {meta.label} rituals complete!</p>
          </div>
        )}

        {/* Ritual Cards */}
        <div className="space-y-3">
          {loading ? (
            Array(3).fill(0).map((_, i) => (
              <div key={i} className="h-24 bg-slate-900 rounded-2xl animate-pulse" />
            ))
          ) : rituals.map(ritual => (
            <div key={ritual.id}
              className={`bg-slate-900 border rounded-2xl p-5 flex items-center gap-4 transition-all duration-300
                ${ritual.completed ? 'border-emerald-700/40 opacity-70' : 'border-slate-800 hover:border-slate-700'}`}>
              <div className="text-3xl shrink-0">{ritual.icon}</div>
              <div className="flex-1 min-w-0">
                <p className={`font-semibold text-sm ${ritual.completed ? 'line-through text-slate-500' : 'text-white'}`}>
                  {ritual.title}
                </p>
                <p className="text-slate-400 text-xs mt-0.5">{ritual.description}</p>
                <p className="text-slate-600 text-xs mt-1">{ritual.duration}</p>
              </div>
              <button
                onClick={() => !ritual.completed && complete(ritual.id)}
                disabled={ritual.completed || completing === ritual.id}
                className={`w-8 h-8 rounded-full border-2 flex items-center justify-center shrink-0 transition-all
                  ${ritual.completed
                    ? 'bg-emerald-500 border-emerald-500 text-white'
                    : 'border-slate-600 hover:border-blue-500'}`}>
                {ritual.completed && <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7"/></svg>}
              </button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
