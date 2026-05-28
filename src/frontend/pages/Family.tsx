import React, { useEffect, useState } from 'react';
import { useAuth } from '../hooks/useAuth';

interface Member {
  id: number;
  name: string;
  role: 'owner' | 'member';
  day_streak: number;
}

interface Group {
  id: number;
  name: string;
  invite_code: string;
  role: 'owner' | 'member';
}

export default function Family() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [group, setGroup] = useState<Group | null>(null);
  const [members, setMembers] = useState<Member[]>([]);
  const [toast, setToast] = useState<string | null>(null);

  const isPremium = ['premium', 'admin'].includes(user?.role || '') || ['premium', 'admin'].includes(user?.plan || '');

  const showToast = (msg: string) => {
    setToast(msg);
    setTimeout(() => setToast(null), 3000);
  };

  const fetchFamily = async () => {
    try {
      const r = await fetch('/api/family/my-group');
      if (r.status === 403) {
        setLoading(false);
        return;
      }
      const data = await r.json();
      setGroup(data.group);
      setMembers(data.members || []);
    } catch {
      showToast("Error loading family circle.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isPremium) {
      fetchFamily();
    } else {
      setLoading(false);
    }
  }, [isPremium]);

  const handleCreate = async () => {
    const name = prompt("Enter your Family Circle Name:", "My Family");
    if (!name) return;
    try {
      const r = await fetch('/api/family/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name }),
      });
      if (r.ok) {
        showToast("Family circle successfully created!");
        fetchFamily();
      }
    } catch {
      showToast("Failed to create group.");
    }
  };

  const handleJoin = async () => {
    const code = prompt("Enter your 6-character Invite Code:");
    if (!code) return;
    try {
      const r = await fetch('/api/family/join', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code }),
      });
      const data = await r.json();
      if (r.ok) {
        showToast("Successfully joined the family circle!");
        fetchFamily();
      } else {
        showToast(data.error || "Failed to join group.");
      }
    } catch {
      showToast("Error joining group.");
    }
  };

  const handleRemove = async (memberId: number) => {
    if (!confirm("Are you sure you want to remove this member?")) return;
    try {
      const r = await fetch('/api/family/remove_member', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: memberId }),
      });
      if (r.ok) {
        showToast("Member removed.");
        fetchFamily();
      }
    } catch {
      showToast("Error removing member.");
    }
  };

  const handleLeave = async () => {
    if (!confirm("Are you sure you want to leave this circle? If you are the owner, this will delete the group.")) return;
    try {
      const r = await fetch('/api/family/leave', { method: 'POST' });
      if (r.ok) {
        showToast("You left the family circle.");
        setGroup(null);
        setMembers([]);
      }
    } catch {
      showToast("Error leaving group.");
    }
  };

  const copyCode = () => {
    if (group?.invite_code) {
      navigator.clipboard.writeText(group.invite_code);
      showToast("Invite code copied to clipboard!");
    }
  };

  if (loading) {
    return (
      <div className="min-h-[60vh] flex items-center justify-center">
        <div className="w-8 h-8 border-2 border-slate-700 border-t-[#c5a572] rounded-full animate-spin" />
      </div>
    );
  }

  // Non-Premium gate
  if (!isPremium) {
    return (
      <div className="max-w-md mx-auto my-12 bg-white/5 border border-white/10 rounded-3xl p-8 text-center backdrop-blur-md shadow-2xl relative overflow-hidden">
        <div className="absolute -bottom-20 -right-20 w-44 h-44 bg-[#c5a572]/10 rounded-full blur-2xl" />
        <div className="text-5xl mb-6">💎</div>
        <h2 className="font-serif text-3xl font-light mb-4">Solen Premium Circle</h2>
        <p className="text-slate-400 text-sm leading-relaxed mb-8">
          Family Sharing is a premium feature. Share your wellness path with up to 4 loved ones, compare active streaks, and support each other's recovery journey.
        </p>
        <button className="w-full bg-[#c5a572] hover:bg-[#d9b884] text-[#1a1008] font-semibold py-3.5 rounded-2xl transition duration-300">
          Upgrade to Premium
        </button>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      {toast && (
        <div className="fixed bottom-24 left-1/2 -translate-x-1/2 bg-[#1a1a24] text-white text-xs px-6 py-3.5 rounded-full z-50 shadow-2xl border border-white/5 font-medium transition animate-bounce">
          {toast}
        </div>
      )}

      <div className="text-center mb-10">
        <span className="text-xs font-semibold tracking-widest text-[#c5a572] uppercase">SUPPORT CIRCLE</span>
        <h1 className="font-serif text-4xl font-light mt-2 text-white">Family Sharing</h1>
        <p className="text-slate-400 text-sm mt-2">Wellness is better when shared with those who care.</p>
      </div>

      {!group ? (
        <div className="bg-white/5 border border-white/10 rounded-3xl p-8 text-center backdrop-blur-md">
          <div className="text-4xl mb-4">🫂</div>
          <h3 className="font-serif text-2xl font-light mb-2">No Active Circle</h3>
          <p className="text-slate-400 text-xs max-w-sm mx-auto mb-6 leading-relaxed">
            You aren't in a family circle yet. Create one to invite members, or enter an active invite code to join a loved one.
          </p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <button 
              onClick={handleCreate}
              className="bg-[#c5a572] hover:bg-[#d9b884] text-[#1a1008] text-sm font-semibold px-6 py-3 rounded-xl transition hover:scale-105 active:scale-95"
            >
              Create a Circle
            </button>
            <button 
              onClick={handleJoin}
              className="border border-white/10 hover:border-white/20 bg-white/3 hover:bg-white/5 text-slate-300 hover:text-white text-sm font-semibold px-6 py-3 rounded-xl transition"
            >
              Join with Code
            </button>
          </div>
        </div>
      ) : (
        <div className="space-y-6">
          <div className="bg-[#0e0e1a]/80 border border-white/5 rounded-3xl p-6 shadow-xl">
            <div className="flex justify-between items-center pb-4 border-b border-white/5 mb-4">
              <h3 className="font-serif text-2xl font-light text-white">👨‍👩‍👧‍👦 {group.name}</h3>
              <span className="text-[10px] bg-[#c5a572]/10 border border-[#c5a572]/20 text-[#c5a572] uppercase tracking-wider px-2 py-0.5 rounded">
                Active Circle
              </span>
            </div>

            <div className="divide-y divide-white/5">
              {members.map(member => (
                <div key={member.id} className="flex items-center gap-4 py-3.5">
                  <div className="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center font-bold text-[#c5a572] border border-white/5">
                    {member.name.charAt(0).toUpperCase()}
                  </div>
                  
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium text-white">{member.name}</span>
                      {member.role === 'owner' && (
                        <span className="text-[9px] bg-white/10 text-slate-400 uppercase tracking-widest px-1.5 py-0.2 rounded font-semibold">
                          Owner
                        </span>
                      )}
                    </div>
                    <div className="text-[11px] text-[#c5a572] mt-0.5 font-medium flex items-center gap-1">
                      🔥 {member.day_streak || 0} day streak
                    </div>
                  </div>

                  {group.role === 'owner' && member.role !== 'owner' && (
                    <button 
                      onClick={() => handleRemove(member.id)}
                      className="text-xs text-red-400 hover:text-red-300 font-medium transition"
                    >
                      Remove
                    </button>
                  )}
                </div>
              ))}
            </div>

            {group.role === 'owner' && members.length < 4 && (
              <div className="mt-6 bg-[#07070f] border border-[#c5a572]/20 border-dashed rounded-2xl p-5 text-center">
                <span className="text-[10px] tracking-wider text-slate-500 uppercase">INVITE LOVED ONES</span>
                <div className="font-mono text-3xl font-bold tracking-[6px] text-[#c5a572] my-3">
                  {group.invite_code}
                </div>
                <button 
                  onClick={copyCode}
                  className="bg-[#c5a572]/10 hover:bg-[#c5a572] border border-[#c5a572]/30 text-[#c5a572] hover:text-[#1a1008] text-xs font-semibold px-4 py-2 rounded-full transition"
                >
                  Copy Invite Code
                </button>
              </div>
            )}
          </div>

          <button 
            onClick={handleLeave}
            className="w-full border border-red-500/20 bg-red-500/5 hover:bg-red-500/10 text-red-400 font-semibold py-3.5 rounded-2xl transition duration-200"
          >
            Leave Family Circle
          </button>
        </div>
      )}
    </div>
  );
}
