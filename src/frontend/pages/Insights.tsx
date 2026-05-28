import React, { useEffect, useState } from 'react';
import { useAuth } from '../hooks/useAuth';

interface Insight {
  id: number;
  summary: string;
  themes: string;
  session_date: string;
}

interface Intelligence {
  life_phase: string;
  evolution: string;
}

export default function Insights() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState<any>(null);
  const [intel, setIntel] = useState<Intelligence | null>(null);
  const [generatingIntel, setGeneratingIntel] = useState(false);
  const [toast, setToast] = useState<string | null>(null);

  // Daily SoulArt States
  const [artUrl, setArtUrl] = useState<string | null>(null);
  const [generatingArt, setGeneratingArt] = useState(false);
  const [artPrompt, setArtPrompt] = useState<string | null>(null);


  const isPremium = ['premium', 'admin'].includes(user?.role || '') || ['premium', 'admin'].includes(user?.plan || '');

  const showToast = (msg: string) => {
    setToast(msg);
    setTimeout(() => setToast(null), 3000);
  };

  const fetchInsights = async () => {
    try {
      const r = await fetch('/api/insights');
      const data = await r.json();
      setStats(data);
    } catch {
      showToast("Error loading analytics.");
    } finally {
      setLoading(false);
    }
  };

  const fetchLifeIntelligence = async () => {
    if (!isPremium) return;
    setGeneratingIntel(true);
    try {
      const r = await fetch('/api/insights/life-intelligence', { method: 'POST' });
      const data = await r.json();
      if (data.intelligence) {
        setIntel(data.intelligence);
      } else {
        showToast("Add some daily sessions first to generate evolution logs!");
      }
    } catch {
      showToast("Could not generate AI analysis.");
    } finally {
      setGeneratingIntel(false);
    }
  };

  const handleExport = async () => {
    try {
      const r = await fetch('/api/insights/export');
      const data = await r.json();
      
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `solen-wellness-diary-${new Date().toISOString().slice(0, 10)}.json`;
      a.click();
      showToast("Diary export downloaded!");
    } catch {
      showToast("Failed to export diary.");
    }
  };

  const handleShare = () => {
    const text = `I'm tracking my wellness path on Solen! Active streak: ${stats?.streak || 0} days, average mood: ${stats?.avgMood || '—'}/10. Join me!`;
    navigator.clipboard.writeText(text);
    showToast("Status copied to clipboard!");
  };

  const generateSoulArt = async () => {
    setGeneratingArt(true);
    try {
      const r = await fetch('/api/ai/generate-art', { method: 'POST' });
      const data = await r.json();
      if (data.image) {
        setArtUrl(data.image);
        setArtPrompt(data.prompt);
        showToast("Personalized SoulArt generated!");
      } else {
        showToast(data.error || "Failed to generate artwork.");
      }
    } catch {
      showToast("Error connecting to AI art service.");
    } finally {
      setGeneratingArt(false);
    }
  };

  const downloadArt = () => {
    if (!artUrl) return;
    const a = document.createElement('a');
    a.href = artUrl;
    a.download = `solen-soulart-${new Date().toISOString().slice(0, 10)}.png`;
    a.click();
  };


  useEffect(() => {
    fetchInsights();
  }, []);

  if (loading) {
    return (
      <div className="min-h-[60vh] flex items-center justify-center">
        <div className="w-8 h-8 border-2 border-slate-700 border-t-[#c5a572] rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      {toast && (
        <div className="fixed bottom-24 left-1/2 -translate-x-1/2 bg-[#1a1a24] text-white text-xs px-6 py-3.5 rounded-full z-50 shadow-2xl border border-white/5 font-medium transition">
          {toast}
        </div>
      )}

      <div className="flex flex-col md:flex-row justify-between items-center gap-4 mb-10 pb-6 border-b border-white/5">
        <div>
          <span className="text-xs font-semibold tracking-widest text-[#c5a572] uppercase">JOURNEY SUMMARY</span>
          <h1 className="font-serif text-4xl font-light mt-2 text-white">Wellness Insights</h1>
          <p className="text-slate-400 text-sm mt-1">Deep patterns and evolution metrics captured across your sessions.</p>
        </div>
        
        <button 
          onClick={handleExport}
          className="border border-white/10 hover:border-white/20 bg-white/3 hover:bg-white/5 text-slate-300 hover:text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition flex items-center gap-2"
        >
          📥 Download Wellness Diary (JSON)
        </button>
      </div>

      {/* LONG-TERM AI EVOLUTION ANALYZER BANNER */}
      {isPremium ? (
        <div className="bg-gradient-to-r from-[#c5a572]/15 to-violet-600/10 border border-[#c5a572]/20 rounded-3xl p-6 md:p-8 mb-8 relative overflow-hidden">
          <div className="absolute -bottom-10 -right-10 w-36 h-36 bg-[#c5a572]/10 rounded-full blur-2xl" />
          
          <div className="flex flex-col md:flex-row items-start md:items-center gap-6 relative z-10">
            <div className="text-4xl">✨</div>
            <div className="flex-1">
              <span className="text-[10px] tracking-wider text-[#c5a572] font-semibold uppercase">LONG-TERM EVOLUTION ANALYSIS</span>
              
              {intel ? (
                <div className="mt-2">
                  <h3 className="font-serif text-2xl font-light text-white mb-2">{intel.life_phase}</h3>
                  <p className="text-slate-300 text-xs leading-relaxed max-w-2xl whitespace-pre-line">{intel.evolution}</p>
                </div>
              ) : (
                <div className="mt-2">
                  <h3 className="font-serif text-2xl font-light text-white mb-2">Uncover Hidden Patterns</h3>
                  <p className="text-slate-400 text-xs leading-relaxed max-w-xl">
                    Run our clinical evolution model on your past session history. Solen will map your emotional milestones and synthesize a personalized growth summary.
                  </p>
                </div>
              )}
            </div>

            {!intel && (
              <button 
                onClick={fetchLifeIntelligence}
                disabled={generatingIntel}
                className="bg-[#c5a572] hover:bg-[#d9b884] disabled:opacity-50 text-[#1a1008] text-xs font-semibold px-5 py-3 rounded-xl transition flex-shrink-0"
              >
                {generatingIntel ? "Analyzing diaries..." : "Generate Evolution Analysis"}
              </button>
            )}
          </div>
        </div>
      ) : (
        <div className="bg-[#0e0e1a]/85 border border-[#c5a572]/15 rounded-3xl p-6 mb-8 flex items-center justify-between gap-6">
          <div>
            <span className="text-[10px] text-[#c5a572] font-bold uppercase tracking-widest">PREMIUM EVOLUTION BANNER</span>
            <h3 className="font-serif text-xl font-light text-white mt-1">Unlock Long-Term AI Analysis</h3>
            <p className="text-slate-400 text-xs mt-1">Analyze weeks of diary entries to isolate mood slumps, trigger habits, and discover milestones.</p>
          </div>
          <button className="bg-[#c5a572] hover:bg-[#d9b884] text-[#1a1008] text-xs font-semibold px-4 py-2.5 rounded-xl transition">
            Unlock
          </button>
        </div>
      )}

      <div className="grid md:grid-cols-3 gap-8">
        
        {/* THE GROWTH CARD ASIDE */}
        <aside className="bg-[#0e0e1a] border border-white/5 rounded-3xl p-6 shadow-xl text-center self-start relative overflow-hidden">
          <div className="absolute -bottom-16 -right-16 w-32 h-32 bg-[#c5a572]/5 rounded-full blur-2xl" />
          
          <span className="font-serif text-2xl text-[#c5a572] italic block">Solen</span>
          <span className="text-[10px] text-slate-500 uppercase tracking-widest mt-1 block">Evolution Report</span>
          
          <h2 className="font-serif text-3xl font-light text-white my-6">Building Resilience</h2>

          <div className="grid grid-cols-2 gap-4 my-6">
            <div className="bg-white/3 border border-white/5 rounded-2xl py-3">
              <span className="font-serif text-3xl text-[#c5a572] leading-none">{stats?.streak || 0}</span>
              <span className="text-[9px] text-slate-500 uppercase tracking-wider block mt-1">Streak</span>
            </div>
            <div className="bg-white/3 border border-white/5 rounded-2xl py-3">
              <span className="font-serif text-3xl text-[#c5a572] leading-none">{stats?.avgMood || '—'}</span>
              <span className="text-[9px] text-slate-500 uppercase tracking-wider block mt-1">Avg Mood</span>
            </div>
            <div className="bg-white/3 border border-white/5 rounded-2xl py-3">
              <span className="font-serif text-3xl text-[#c5a572] leading-none">{stats?.epCount || 0}</span>
              <span className="text-[9px] text-slate-500 uppercase tracking-wider block mt-1">Insights</span>
            </div>
            <div className="bg-white/3 border border-white/5 rounded-2xl py-3">
              <span className="font-serif text-3xl text-[#c5a572] leading-none">{stats?.days || 0}</span>
              <span className="text-[9px] text-slate-500 uppercase tracking-wider block mt-1">Days Logs</span>
            </div>
          </div>

          {stats?.frequentThemes?.length > 0 && (
            <div className="mb-6">
              <span className="text-[10px] text-slate-500 uppercase tracking-widest block mb-2">Core Focus Themes</span>
              <div className="flex flex-wrap justify-center gap-1.5">
                {stats.frequentThemes.map((theme: string) => (
                  <span key={theme} className="bg-white/5 border border-white/5 text-slate-300 text-[11px] px-2.5 py-1 rounded-full font-medium">
                    #{theme}
                  </span>
                ))}
              </div>
            </div>
          )}

          <button 
            onClick={handleShare}
            className="w-full bg-[#c5a572] hover:bg-[#d9b884] text-[#1a1008] text-xs font-semibold py-3 rounded-xl transition"
          >
            Share Journey Snapshot
          </button>
        </aside>

        {/* DAILY SOULART CARD */}
        <div className="bg-[#0e0e1a] border border-white/5 rounded-3xl p-6 shadow-xl relative overflow-hidden mt-6 text-center">
          <div className="absolute -top-12 -left-12 w-24 h-24 bg-violet-600/10 rounded-full blur-xl" />
          <span className="text-[10px] text-slate-500 uppercase tracking-widest block font-medium">PREMIUM FEATURE</span>
          <h3 className="font-serif text-2xl font-light text-white mt-2 mb-1">Daily SoulArt</h3>
          <p className="text-slate-400 text-[11px] leading-relaxed mb-5">
            Synthesize your emotional state into a custom serene mobile wallpaper art.
          </p>

          {artUrl ? (
            <div className="space-y-4">
              <div className="relative group rounded-2xl overflow-hidden border border-white/10 aspect-[9/16] max-h-72 mx-auto bg-slate-950 shadow-2xl">
                <img src={artUrl} alt="Personalized SoulArt" className="w-full h-full object-cover" />
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition duration-300 flex items-end p-4">
                  <p className="text-[10px] text-slate-300 text-left line-clamp-3 italic">"{artPrompt}"</p>
                </div>
              </div>
              
              <div className="flex gap-2">
                <button
                  onClick={downloadArt}
                  className="flex-1 bg-[#c5a572] hover:bg-[#d9b884] text-[#1a1008] text-xs font-semibold py-2.5 rounded-xl transition"
                >
                  📥 Download
                </button>
                <button
                  onClick={generateSoulArt}
                  disabled={generatingArt}
                  className="bg-white/5 hover:bg-white/10 text-slate-300 text-xs font-semibold px-3 py-2.5 rounded-xl transition border border-white/5"
                >
                  🔄 Redo
                </button>
              </div>
            </div>
          ) : (
            <div className="space-y-5">
              <div className="rounded-2xl border border-dashed border-white/10 aspect-[9/16] max-h-48 mx-auto flex flex-col items-center justify-center p-4 bg-white/5">
                {generatingArt ? (
                  <div className="space-y-3 text-center">
                    <div className="w-6 h-6 border-2 border-slate-700 border-t-[#c5a572] rounded-full animate-spin mx-auto" />
                    <p className="text-[10px] text-slate-400 animate-pulse">Dreaming Calm Vibe...</p>
                  </div>
                ) : (
                  <div className="text-center text-slate-600">
                    <span className="text-3xl block mb-2">🎨</span>
                    <p className="text-[10px] uppercase tracking-wider font-semibold text-slate-500">Art Awaiting Vibe</p>
                  </div>
                )}
              </div>

              <button
                onClick={generateSoulArt}
                disabled={generatingArt}
                className="w-full bg-gradient-to-r from-[#c5a572] to-amber-600 hover:from-[#d9b884] hover:to-amber-500 disabled:opacity-50 text-[#1a1008] text-xs font-semibold py-3 rounded-xl transition shadow-lg shadow-[#c5a572]/5"
              >
                {generatingArt ? "Synthesizing Art..." : "Generate Calm Art"}
              </button>
            </div>
          )}
        </div>


        {/* DIARY LIST MAIN */}
        <main className="md:col-span-2 space-y-4">
          <h3 className="font-serif text-2xl font-light text-white mb-4">Journey Highlights</h3>

          {!stats?.insights?.length ? (
            <div className="bg-white/3 border border-white/5 rounded-3xl p-12 text-center text-slate-500">
              <div className="text-4xl mb-4">📖</div>
              <p className="text-sm font-medium">No major milestones recorded yet.</p>
              <p className="text-xs mt-1">Have continuous daily sessions with your coach to extract evolution insights.</p>
            </div>
          ) : (
            <div className="space-y-4">
              {stats.insights.map((insight: any) => (
                <div key={insight.id} className="bg-[#0e0e1a]/80 border border-white/5 rounded-2xl p-5 border-l-2 border-l-[#c5a572] hover:scale-[1.005] transition duration-200">
                  <div className="text-[10px] text-slate-500 uppercase tracking-wider mb-2 font-medium">
                    {new Date(insight.session_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
                  </div>
                  
                  <p className="text-slate-300 text-xs leading-relaxed">{insight.summary}</p>
                  
                  {insight.themes && (
                    <div className="flex flex-wrap gap-1.5 mt-3">
                      {insight.themes.split(',').map((t: string) => (
                        <span key={t} className="text-[9px] bg-white/5 text-[#c5a572] px-2 py-0.5 rounded">
                          {t.trim()}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </main>

      </div>
    </div>
  );
}
