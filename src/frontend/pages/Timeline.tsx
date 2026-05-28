import React, { useEffect, useState } from 'react';

const SCORE_COLOR = (s: number) =>
  s >= 8 ? 'bg-emerald-500' : s >= 6 ? 'bg-blue-500' : s >= 4 ? 'bg-yellow-500' : 'bg-red-500';

const SCORE_EMOJI = (s: number) =>
  s >= 8 ? '😊' : s >= 6 ? '🙂' : s >= 4 ? '😐' : s >= 2 ? '😔' : '😢';

export default function Timeline() {
  const [data, setData] = useState<any>(null);
  const [days, setDays] = useState(30);
  const [loading, setLoading] = useState(true);
  const [showMoodForm, setShowMoodForm] = useState(false);
  const [moodScore, setMoodScore] = useState(7);
  const [moodNote, setMoodNote] = useState('');
  const [saving, setSaving] = useState(false);

  const load = async (d: number) => {
    setLoading(true);
    const r = await fetch(`/api/timeline?days=${d}`, { credentials: 'include' });
    setData(await r.json());
    setLoading(false);
  };

  useEffect(() => { load(days); }, [days]);

  const logMood = async () => {
    setSaving(true);
    await fetch('/api/mood', {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ score: moodScore, emoji: SCORE_EMOJI(moodScore), notes: moodNote }),
    });
    setSaving(false);
    setShowMoodForm(false);
    setMoodNote('');
    load(days);
  };

  return (
    <div className="min-h-full bg-slate-950 p-4 md:p-8">
      <div className="max-w-2xl mx-auto">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-2xl font-bold text-white">Emotional Timeline</h2>
            <p className="text-slate-400 text-sm mt-1">Your growth journey</p>
          </div>
          <button onClick={() => setShowMoodForm(!showMoodForm)}
            className="bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium px-4 py-2 rounded-xl transition">
            + Log Mood
          </button>
        </div>

        {/* Mood Form */}
        {showMoodForm && (
          <div className="bg-slate-900 border border-slate-700 rounded-2xl p-5 mb-6 space-y-4">
            <p className="text-white font-medium">How are you feeling? ({moodScore}/10)</p>
            <div className="flex items-center gap-3">
              <span className="text-2xl">{SCORE_EMOJI(moodScore)}</span>
              <input type="range" min={1} max={10} value={moodScore}
                onChange={e => setMoodScore(+e.target.value)}
                className="flex-1 accent-blue-500" />
            </div>
            <textarea rows={2} value={moodNote} onChange={e => setMoodNote(e.target.value)}
              placeholder="Optional note…"
              className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-blue-500 resize-none" />
            <div className="flex gap-2">
              <button onClick={logMood} disabled={saving}
                className="bg-blue-600 hover:bg-blue-500 text-white text-sm px-4 py-2 rounded-lg transition disabled:opacity-50">
                {saving ? 'Saving…' : 'Save Mood'}
              </button>
              <button onClick={() => setShowMoodForm(false)} className="text-slate-400 hover:text-white text-sm px-4 py-2 rounded-lg transition">
                Cancel
              </button>
            </div>
          </div>
        )}

        {/* Stats Row */}
        <div className="grid grid-cols-3 gap-3 mb-6">
          {[
            { label: 'Avg Mood', value: data?.moodAvg ? `${data.moodAvg}/10` : '—' },
            { label: 'Milestones', value: data?.milestones?.length ?? 0 },
            { label: 'Entries', value: data?.moods?.length ?? 0 },
          ].map(s => (
            <div key={s.label} className="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
              <p className="text-slate-400 text-xs mb-1">{s.label}</p>
              <p className="text-white font-bold text-xl">{loading ? '…' : s.value}</p>
            </div>
          ))}
        </div>

        {/* Filter */}
        <div className="flex gap-2 mb-6">
          {[7, 30, 90].map(d => (
            <button key={d} onClick={() => setDays(d)}
              className={`px-4 py-1.5 rounded-lg text-sm font-medium transition ${days === d ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'}`}>
              {d}d
            </button>
          ))}
        </div>

        {/* Timeline */}
        <div className="space-y-3">
          {loading ? Array(5).fill(0).map((_, i) => (
            <div key={i} className="h-16 bg-slate-900 rounded-xl animate-pulse" />
          )) : data?.moods?.length === 0 ? (
            <div className="text-center text-slate-500 py-12">
              <p className="text-4xl mb-3">📭</p>
              <p>No mood entries yet. Log your first mood!</p>
            </div>
          ) : data?.moods?.map((m: any) => (
            <div key={m.id} className="bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 flex items-center gap-4">
              <div className={`w-2 h-10 rounded-full shrink-0 ${SCORE_COLOR(m.score)}`} />
              <span className="text-2xl">{m.emoji || SCORE_EMOJI(m.score)}</span>
              <div className="flex-1 min-w-0">
                <p className="text-white text-sm font-medium">{m.label || `Score: ${m.score}/10`}</p>
                {m.notes && <p className="text-slate-400 text-xs truncate mt-0.5">{m.notes}</p>}
              </div>
              <div className="text-right shrink-0">
                <p className="text-slate-400 text-xs">{m.logged_date || m.loggedDate}</p>
                <p className="text-white font-bold text-sm">{m.score}<span className="text-slate-500 text-xs">/10</span></p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
