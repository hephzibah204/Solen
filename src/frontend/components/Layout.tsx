import React from 'react';

interface Props {
  view: string;
  setView: (v: string) => void;
  user: { name: string; plan: string } | null;
  onLogout: () => void;
}

const navItems = [
  { id: 'dashboard', icon: '🏠', label: 'Home' },
  { id: 'chat',      icon: '💬', label: 'Coach' },
  { id: 'rituals',   icon: '🌅', label: 'Rituals' },
  { id: 'timeline',  icon: '📈', label: 'Growth' },
  { id: 'insights',  icon: '📊', label: 'Insights' },
  { id: 'family',    icon: '🫂', label: 'Family' },
  { id: 'profile',   icon: '👤', label: 'Profile' },
];

export default function Layout({ view, setView, user, onLogout, children }: React.PropsWithChildren<Props>) {
  return (
    <div className="flex h-screen bg-slate-950 text-white overflow-hidden">
      {/* Sidebar — desktop */}
      <aside className="hidden md:flex flex-col w-64 bg-slate-900 border-r border-slate-800 py-6 px-4 shrink-0">
        {/* Logo */}
        <div className="mb-8 px-2">
          <h1 className="text-2xl font-bold bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">Solen</h1>
          <p className="text-xs text-slate-500 mt-0.5">AI Wellness Coach</p>
        </div>

        {/* Nav */}
        <nav className="flex-1 space-y-1">
          {navItems.map(item => (
            <button
              key={item.id}
              onClick={() => setView(item.id)}
              className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                ${view === item.id
                  ? 'bg-blue-600/20 text-blue-400 border border-blue-500/30'
                  : 'text-slate-400 hover:text-white hover:bg-slate-800'}`}
            >
              <span className="text-lg">{item.icon}</span>
              {item.label}
            </button>
          ))}
        </nav>

        {/* User */}
        {user && (
          <div className="mt-4 border-t border-slate-800 pt-4">
            <div className="flex items-center gap-3 px-2 mb-3">
              <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-violet-500 flex items-center justify-center text-xs font-bold shrink-0">
                {user.name.charAt(0).toUpperCase()}
              </div>
              <div className="min-w-0">
                <p className="text-sm font-medium text-white truncate">{user.name}</p>
                <p className="text-xs text-slate-500 capitalize">{user.plan} plan</p>
              </div>
            </div>
            <button onClick={onLogout} className="w-full text-left text-xs text-slate-500 hover:text-red-400 px-2 py-1 transition-colors">
              Sign out →
            </button>
          </div>
        )}
      </aside>

      {/* Main */}
      <div className="flex-1 flex flex-col min-h-0 overflow-hidden">
        <main className="flex-1 overflow-y-auto pb-20 md:pb-0">
          {children}
        </main>

        {/* Bottom Nav — mobile */}
        <nav className="md:hidden fixed bottom-0 left-0 right-0 bg-slate-900/95 backdrop-blur border-t border-slate-800 px-2 py-2 z-50">
          <div className="flex justify-around">
            {navItems.map(item => (
              <button
                key={item.id}
                onClick={() => setView(item.id)}
                className={`flex flex-col items-center gap-0.5 px-3 py-1.5 rounded-lg transition-all
                  ${view === item.id ? 'text-blue-400' : 'text-slate-500'}`}
              >
                <span className="text-xl">{item.icon}</span>
                <span className="text-[10px] font-medium">{item.label}</span>
              </button>
            ))}
          </div>
        </nav>
      </div>
    </div>
  );
}
