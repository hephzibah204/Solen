import React, { useEffect, useState } from 'react';
import { useAuth } from '../hooks/useAuth';

import StreakLearning from '../components/StreakLearning';

interface Stats {
  streak: number;
  todayMood: { score: number; emoji: string; label: string } | null;
  weekMoodAvg: number | null;
  ritualsDone: number;
  plan: string;
  growthStage: string;
}

const PLAN_COLORS: Record<string, string> = {
  free: 'bg-slate-700 text-slate-300',
  plus: 'bg-blue-600/30 text-blue-300',
  pro: 'bg-violet-600/30 text-violet-300',
  premium: 'bg-amber-600/30 text-amber-300',
};

export default function Dashboard({ setView }: { setView: (v: string) => void }) {
  const { user } = useAuth();
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);

  const refreshStats = () => {
    fetch('/api/stats', { credentials: 'include' })
      .then(r => r.json()).then(setStats);
  };

  useEffect(() => {
    fetch('/api/stats', { credentials: 'include' })
      .then(r => r.json()).then(setStats).finally(() => setLoading(false));
  }, []);

  const quickActions = [
    { icon: '💬', label: 'Chat with Coach', sub: 'AI-powered session', view: 'chat', color: 'from-blue-600 to-blue-700' },
    { icon: '😊', label: 'Log Mood', sub: 'How are you feeling?', view: 'timeline', color: 'from-violet-600 to-violet-700' },
    { icon: '🌅', label: 'Daily Ritual', sub: `${stats?.ritualsDone ?? 0} done today`, view: 'rituals', color: 'from-amber-600 to-orange-600' },
    { icon: '📈', label: 'View Growth', sub: 'Timeline & milestones', view: 'timeline', color: 'from-emerald-600 to-teal-600' },
  ];

  return (
    <div className="min-h-full bg-slate-950 p-4 md:p-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-2xl font-bold text-white">
              Good {new Date().getHours() < 12 ? 'morning' : new Date().getHours() < 17 ? 'afternoon' : 'evening'},{' '}
              <span className="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">
                {user?.name?.split(' ')[0]} 👋
              </span>
            </h2>
            <p className="text-slate-400 mt-1">Here's your wellness snapshot</p>
          </div>
          <span className={`text-xs font-semibold px-3 py-1.5 rounded-full capitalize ${PLAN_COLORS[user?.plan ?? 'free']}`}>
            {user?.plan ?? 'free'}
          </span>
        </div>
      </div>

      {/* Stat Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        {[
          { label: "Today's Mood", value: stats?.todayMood ? `${stats.todayMood.emoji} ${stats.todayMood.score}/10` : '— Not logged', icon: '🧠' },
          { label: 'Day Streak', value: `${stats?.streak ?? 0} 🔥`, icon: '🏆' },
          { label: 'Weekly Avg', value: stats?.weekMoodAvg ? `${stats.weekMoodAvg}/10` : '—', icon: '📊' },
          { label: 'Rituals Today', value: `${stats?.ritualsDone ?? 0} done`, icon: '✅' },
        ].map(card => (
          <div key={card.label} className="bg-slate-900 border border-slate-800 rounded-2xl p-5 hover:border-slate-700 transition-colors">
            <div className="text-2xl mb-2">{card.icon}</div>
            <p className="text-slate-400 text-xs mb-1">{card.label}</p>
            <p className="text-white font-semibold text-lg leading-tight">{loading ? '…' : card.value}</p>
          </div>
        ))}
      </div>

      {/* Streak Daily Retention Learning */}
      <div className="mb-8">
        <StreakLearning onCompleted={refreshStats} />
      </div>

      {/* Quick Actions */}
      <h3 className="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Quick Actions</h3>
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        {quickActions.map(action => (
          <button
            key={action.label}
            onClick={() => setView(action.view)}
            className={`bg-gradient-to-br ${action.color} p-5 rounded-2xl text-left hover:scale-[1.02] active:scale-[0.98] transition-all duration-150 shadow-lg`}
          >
            <div className="text-3xl mb-3">{action.icon}</div>
            <p className="text-white font-semibold text-sm">{action.label}</p>
            <p className="text-white/60 text-xs mt-0.5">{action.sub}</p>
          </button>
        ))}
      </div>

      {/* Growth Stage Banner */}
      {stats?.growthStage && (
        <div className="bg-gradient-to-r from-violet-900/50 to-blue-900/50 border border-violet-700/30 rounded-2xl p-5 flex items-center gap-4">
          <div className="text-3xl">🌱</div>
          <div>
            <p className="text-violet-300 text-xs font-semibold uppercase tracking-wider">Growth Stage</p>
            <p className="text-white font-semibold capitalize mt-0.5">{stats.growthStage}</p>
          </div>
        </div>
      )}
    </div>
  );
}
