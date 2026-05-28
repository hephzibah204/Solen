import React, { useState } from 'react';

export default function Landing({ onLogin, onRegister }: { onLogin: () => void; onRegister: () => void }) {
  const [activeFaq, setActiveFaq] = useState<number | null>(null);

  const faqs = [
    {
      q: "Is Solen a replacement for therapy?",
      a: "No. Solen is an empathetic wellness companion, not a licensed therapy or medical service. It works beautifully alongside clinical therapy or as a daily emotional reflection practice."
    },
    {
      q: "How does Solen's memory work?",
      a: "After each conversation, Solen identifies core themes and emotional milestones. In your next session, your coach references these milestones naturally—building a genuine relationship over time instead of starting from zero."
    },
    {
      q: "Is my data private and secure?",
      a: "Absolutely. Solen uses secure sessions and respects your data ownership. You can export your complete conversation diary as a JSON file or delete your account instantly at any time."
    },
    {
      q: "What happens after my free trial?",
      a: "You get 7 days of full, unrestricted access to Solen Pro features with no credit card required. After the trial, you can choose to upgrade to a Pro or Premium plan to continue coaching."
    }
  ];

  return (
    <div className="min-h-screen bg-[#07070f] text-[#f0ede8] font-sans overflow-x-hidden relative selection:bg-[#c5a572] selection:text-[#1a1008]">
      
      {/* Google Fonts Link */}
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&family=Outfit:wght@300;400;500;600;700&display=swap');
        
        .font-serif {
          font-family: 'Cormorant Garamond', serif;
        }
        .font-sans {
          font-family: 'Outfit', sans-serif;
        }
        
        /* Subtle noise texture */
        .noise-bg::before {
          content: '';
          position: fixed;
          inset: 0;
          background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='1'/%3E%3C/svg%3E");
          opacity: 0.02;
          pointer-events: none;
          z-index: 50;
        }

        @keyframes float {
          0%, 100% { transform: translateY(0px) scale(1); }
          50% { transform: translateY(-15px) scale(1.03); }
        }
        @keyframes pulse-slow {
          0%, 100% { opacity: 0.45; }
          50% { opacity: 0.85; }
        }
        .animate-float {
          animation: float 8s ease-in-out infinite;
        }
        .animate-pulse-slow {
          animation: pulse-slow 3s ease-in-out infinite;
        }
      `}</style>

      {/* Grain Overlay */}
      <div className="noise-bg pointer-events-none" />

      {/* Ambient glowing background elements */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div className="absolute -top-[20%] left-1/2 -translate-x-1/2 w-[1000px] h-[600px] bg-gradient-to-b from-[#c5a572]/10 to-transparent rounded-full blur-[140px]" />
        <div className="absolute top-[40%] -right-20 w-[400px] h-[400px] bg-violet-600/5 rounded-full blur-[100px] animate-float" style={{ animationDelay: '2s' }} />
        <div className="absolute bottom-[20%] -left-20 w-[500px] h-[500px] bg-[#c5a572]/5 rounded-full blur-[120px] animate-float" style={{ animationDelay: '0s' }} />
      </div>

      {/* Navigation Header */}
      <nav className="relative z-40 border-b border-white/5 bg-[#07070f]/80 backdrop-blur-xl sticky top-0">
        <div className="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className="font-serif text-3xl font-medium tracking-wide text-[#c5a572]">Sol<span className="text-white">en</span></span>
          </div>
          
          <div className="hidden md:flex items-center gap-8 text-sm font-medium text-slate-400">
            <a href="#problem" className="hover:text-white transition">The Problem</a>
            <a href="#features" className="hover:text-white transition">Features</a>
            <a href="#programs" className="hover:text-white transition">Growth Programs</a>
            <a href="#faq" className="hover:text-white transition">FAQ</a>
          </div>

          <div className="flex items-center gap-4">
            <button onClick={onLogin} className="text-sm font-medium text-slate-300 hover:text-white transition">
              Log in
            </button>
            <button 
              onClick={onRegister}
              className="bg-[#c5a572] hover:bg-[#d9b884] text-[#1a1008] text-sm font-semibold px-5 py-2.5 rounded-full transition-all shadow-lg shadow-[#c5a572]/10 hover:scale-105 active:scale-95"
            >
              Start Free Trial
            </button>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <header className="relative z-10 max-w-5xl mx-auto px-6 pt-16 md:pt-24 pb-20 text-center flex flex-col items-center">
        <div className="inline-flex items-center gap-2 border border-[#c5a572]/30 bg-[#c5a572]/5 text-[#c5a572] text-[11px] font-medium tracking-[0.15em] uppercase px-4 py-1.5 rounded-full mb-8">
          ✦ 7-DAY FREE TRIAL · NO CREDIT CARD
        </div>

        <h1 className="font-serif text-5xl md:text-8xl font-light leading-[1.08] tracking-tight mb-8 max-w-4xl">
          The wellness coach that <br className="hidden md:block"/>
          <em className="text-[#c5a572] font-serif font-light">remembers</em> who you are
        </h1>

        <p className="text-slate-400 text-lg md:text-xl font-light max-w-2xl leading-relaxed mb-12">
          Most apps treat you like a stranger every day. Solen builds on every conversation—learning your triggers, celebrating your milestones, and keeping your recovery active.
        </p>

        {/* Live Chat Simulator Graphic */}
        <div className="w-full max-w-md bg-[#0e0e1a]/95 border border-white/5 rounded-3xl p-5 shadow-2xl shadow-black/80 mb-12 text-left relative overflow-hidden">
          <div className="flex items-center gap-2.5 pb-4 border-b border-white/5 mb-4 text-xs">
            <div className="w-2.5 h-2.5 rounded-full bg-[#c5a572]/60 animate-pulse-slow" />
            <span className="font-serif text-sm text-[#c5a572] italic">Luna · your wellness coach</span>
            <span className="ml-auto text-[10px] text-slate-500 uppercase tracking-widest">● Live</span>
          </div>

          <div className="space-y-4">
            <div className="bg-[#c5a572]/8 border border-[#c5a572]/15 text-[#f0ede8] font-serif italic text-[15px] leading-relaxed p-3.5 rounded-2xl rounded-bl-sm max-w-[88%]">
              "Last week you mentioned the 3 PM slump was triggering a sense of anxiety. How has today felt? I noticed you set an intention to take lunch earlier..."
            </div>
            
            <div className="bg-white/5 border border-white/5 text-[#f0ede8] text-sm leading-relaxed p-3 rounded-2xl rounded-br-sm ml-auto w-fit max-w-[80%] text-right">
              Honestly, lunch helped a lot! Slump avoided. 🌅
            </div>

            <div className="flex gap-1.5 pl-2">
              <span className="w-1.5 h-1.5 rounded-full bg-[#c5a572] opacity-60 animate-bounce" style={{ animationDelay: '0ms' }} />
              <span className="w-1.5 h-1.5 rounded-full bg-[#c5a572] opacity-60 animate-bounce" style={{ animationDelay: '200ms' }} />
              <span className="w-1.5 h-1.5 rounded-full bg-[#c5a572] opacity-60 animate-bounce" style={{ animationDelay: '400ms' }} />
            </div>
          </div>
        </div>

        <div className="flex flex-col sm:flex-row gap-4 w-full sm:w-auto justify-center mb-8">
          <button 
            onClick={onRegister}
            className="bg-[#c5a572] hover:bg-[#d9b884] text-[#1a1008] font-semibold px-8 py-4 rounded-full text-[15px] transition-all shadow-xl shadow-[#c5a572]/20 hover:scale-105 active:scale-98 flex items-center justify-center gap-2"
          >
            Start your free trial
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </button>
          <a 
            href="#problem" 
            className="border border-white/10 hover:border-white/20 bg-white/3 hover:bg-white/5 text-slate-300 hover:text-white font-medium px-8 py-4 rounded-full text-[15px] transition-all text-center"
          >
            See how it works
          </a>
        </div>

        <div className="flex items-center gap-5 text-xs text-slate-500 font-light flex-wrap justify-center">
          <div className="flex items-center gap-1.5">
            <span className="text-[#c5a572] text-sm">★★★★★</span>
            <strong className="text-slate-300 font-medium">4.8/5</strong> average rating
          </div>
          <span>·</span>
          <span>No credit card required</span>
          <span>·</span>
          <span>Cancel anytime</span>
        </div>
      </header>

      {/* Social Proof Stats Bar */}
      <section className="relative z-10 border-y border-white/5 bg-white/[0.015] py-8">
        <div className="max-w-6xl mx-auto px-6 grid grid-cols-2 md:grid-cols-5 gap-6 text-center divide-x-0 md:divide-x divide-white/5">
          <div className="flex flex-col items-center">
            <span className="font-serif text-3xl font-light text-white leading-none">310+</span>
            <span className="text-[10px] uppercase tracking-wider text-slate-500 mt-2">Active Seekers</span>
          </div>
          <div className="flex flex-col items-center">
            <span className="font-serif text-3xl font-light text-white leading-none">4.8 ★</span>
            <span className="text-[10px] uppercase tracking-wider text-slate-500 mt-2">User Rating</span>
          </div>
          <div className="flex flex-col items-center">
            <span className="font-serif text-3xl font-light text-white leading-none">4</span>
            <span className="text-[10px] uppercase tracking-wider text-slate-500 mt-2">Growth Tracks</span>
          </div>
          <div className="flex flex-col items-center">
            <span className="font-serif text-3xl font-light text-white leading-none">7 Days</span>
            <span className="text-[10px] uppercase tracking-wider text-slate-500 mt-2">Free Trial</span>
          </div>
          <div className="flex flex-col items-center col-span-2 md:col-span-1">
            <span className="font-serif text-3xl font-light text-white leading-none">30-day</span>
            <span className="text-[10px] uppercase tracking-wider text-slate-500 mt-2">Satisfaction</span>
          </div>
        </div>
      </section>

      {/* Problem Section */}
      <section id="problem" className="relative z-10 bg-[#0a0a14]/60 border-b border-white/5 py-24">
        <div className="max-w-6xl mx-auto px-6 grid md:grid-cols-2 gap-16 items-center">
          <div>
            <span className="text-[#c5a572] text-[11px] font-semibold uppercase tracking-[0.15em] mb-4 block">THE CORE PROBLEM</span>
            <h2 className="font-serif text-4xl md:text-5xl font-light leading-tight text-white mb-6">
              Most apps treat you like a <br className="hidden md:block" />
              <em className="text-[#c5a572] font-serif font-light">stranger</em> every single day
            </h2>
            <p className="text-slate-400 font-light leading-relaxed mb-6">
              Generic prompts ignore your history. When you explain your anxiety or recovery goals from scratch, the therapeutic value is lost. Real personal growth requires continuous context.
            </p>
          </div>

          <div className="space-y-4">
            {[
              { emoji: '🔄', title: 'The Endless Loop', desc: 'No conversation continuity. You repeat your backstories and struggle triggers repeatedly.' },
              { emoji: '🤖', title: 'Hollow, Sterile Feedback', desc: 'One-size-fits-all responses that feel like templates rather than personal understanding.' },
              { emoji: '📉', title: 'Vanishing Engagement', desc: 'Without context, relationships fail to develop. Most wellness apps are abandoned after a few days.' }
            ].map(pain => (
              <div key={pain.title} className="flex gap-4 p-5 bg-[#0e0e1a]/80 border border-white/5 rounded-2xl hover:border-[#c5a572]/20 transition duration-300">
                <span className="text-2xl mt-1">{pain.emoji}</span>
                <div>
                  <h3 className="text-[15px] font-medium text-white mb-1">{pain.title}</h3>
                  <p className="text-xs text-slate-400 leading-relaxed">{pain.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="relative z-10 py-24">
        <div className="max-w-6xl mx-auto px-6">
          <div className="text-center max-w-3xl mx-auto mb-16">
            <span className="text-[#c5a572] text-[11px] font-semibold uppercase tracking-[0.15em] mb-4 block">SOLEN CAPABILITIES</span>
            <h2 className="font-serif text-4xl md:text-6xl font-light text-white mb-4">
              A coach that <em className="text-[#c5a572] font-serif font-light">evolves</em> with you
            </h2>
            <p className="text-slate-400 font-light">
              We did not build an index of static meditation tracks. We designed an organic coach built on continuity and active growth support.
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-6">
            {[
              { icon: '🧠', title: 'Persistent Memory', desc: 'Quietly extracts key emotional signals and notes to naturally build context for your future daily check-ins.' },
              { icon: '📊', title: 'Mood Timeline & Highlights', desc: 'Allows you to visualize the direct impact of daily intentions, stressors, and recovery routines.' },
              { icon: '🌅', title: 'Curated Daily Rituals', desc: 'Provides structured habits customized for morning, afternoon, and evening phases to establish steady rhythms.' },
              { icon: '🌱', title: 'Guided Growth Tracks', desc: 'Comprehensive 7-day focus tracks containing core deep reflection questions and personal development frameworks.' },
              { icon: '🫂', title: 'Family Sharing Connections', desc: 'Extend support networks by forming private family wellness circles with shared streaks and encouragement.' },
              { icon: '🔒', title: 'Secure & Edge Native', desc: 'All sessions are fully private, lightweight, and deployed entirely on Cloudflare edge infrastructure.' }
            ].map(feat => (
              <div key={feat.title} className="bg-[#0e0e1a]/70 border border-white/5 rounded-2xl p-7 hover:border-[#c5a572]/20 hover:scale-[1.01] transition-all duration-300 flex flex-col justify-between">
                <div>
                  <span className="text-3xl mb-5 block">{feat.icon}</span>
                  <h3 className="font-serif text-2xl font-light text-white mb-3">{feat.title}</h3>
                  <p className="text-slate-400 text-xs leading-relaxed">{feat.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Growth Programs Section */}
      <section id="programs" className="relative z-10 bg-[#0a0a14]/60 border-y border-white/5 py-24">
        <div className="max-w-6xl mx-auto px-6">
          <div className="text-center max-w-3xl mx-auto mb-16">
            <span className="text-[#c5a572] text-[11px] font-semibold uppercase tracking-[0.15em] mb-4 block">ACTIVE DEVELOPMENT</span>
            <h2 className="font-serif text-4xl md:text-6xl font-light text-white mb-4">
              Structured paths for <em className="text-[#c5a572] font-serif font-light">real growth</em>
            </h2>
            <p className="text-slate-400 font-light">
              Participate in specialized 7-day programs containing targeted daily reflections and micro-quizzes.
            </p>
          </div>

          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {[
              { icon: '🫂', title: 'Emotional Integrity', desc: 'Build fundamental self-compassion, unpack personal burdens, and develop cognitive resilience.' },
              { icon: '🌿', title: 'Calm & Ground', desc: 'Incorporate actionable cognitive exercises and breathing styles to confidently dissolve active stressors.' },
              { icon: '🌱', title: 'Becoming Whole', desc: 'A dedicated alignment journey focused on setting core visions and identifying internal blocks.' },
              { icon: '🛡️', title: 'Recovery Pathways', desc: 'Empathetic guidance for maintaining clarity, avoiding HALT cues, and establishing long-term sobriety.' }
            ].map(prog => (
              <div key={prog.title} className="bg-[#07070f]/90 border border-white/5 rounded-2xl p-6 hover:border-[#c5a572]/30 transition duration-300">
                <span className="text-3xl mb-4 block">{prog.icon}</span>
                <h3 className="font-serif text-xl font-medium text-white mb-2">{prog.title}</h3>
                <p className="text-xs text-slate-400 leading-relaxed mb-4">{prog.desc}</p>
                <div className="text-[10px] text-[#c5a572] uppercase tracking-wider font-semibold border-t border-white/5 pt-3">
                  7 Sessions · Daily Prompts
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* FAQ Section */}
      <section id="faq" className="relative z-10 py-24">
        <div className="max-w-4xl mx-auto px-6">
          <div className="text-center mb-16">
            <span className="text-[#c5a572] text-[11px] font-semibold uppercase tracking-[0.15em] mb-4 block">COMMON QUESTIONS</span>
            <h2 className="font-serif text-4xl md:text-5xl font-light text-white">Curious about Solen?</h2>
          </div>

          <div className="space-y-4">
            {faqs.map((faq, idx) => (
              <div 
                key={idx} 
                className="bg-[#0e0e1a]/60 border border-white/5 rounded-2xl overflow-hidden transition-all duration-300 cursor-pointer"
                onClick={() => setActiveFaq(activeFaq === idx ? null : idx)}
              >
                <div className="p-6 flex items-center justify-between text-left">
                  <h3 className="font-serif text-lg md:text-xl font-light text-white pr-4">{faq.q}</h3>
                  <span className={`text-[#c5a572] text-xl transition-transform duration-300 ${activeFaq === idx ? 'rotate-45' : ''}`}>＋</span>
                </div>
                
                <div className={`transition-all duration-300 ease-in-out ${activeFaq === idx ? 'max-h-48 border-t border-white/5 opacity-100 p-6' : 'max-h-0 opacity-0 pointer-events-none'}`}>
                  <p className="text-xs text-slate-400 leading-relaxed">{faq.a}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Call to Action Section */}
      <section className="relative z-10 border-t border-white/5 py-24 bg-gradient-to-b from-transparent to-[#c5a572]/3">
        <div className="max-w-4xl mx-auto px-6 text-center">
          <h2 className="font-serif text-5xl md:text-7xl font-light text-white mb-6">
            Reflect · <em className="text-[#c5a572] font-serif font-light">Recover</em> · Rise
          </h2>
          <p className="text-slate-400 text-base md:text-lg font-light max-w-md mx-auto mb-10 leading-relaxed">
            Begin your secure 7-day wellness exploration today. No commitment, cancel with a single click.
          </p>

          <button 
            onClick={onRegister}
            className="bg-[#c5a572] hover:bg-[#d9b884] text-[#1a1008] font-semibold px-10 py-4.5 rounded-full text-base transition-all shadow-2xl shadow-[#c5a572]/20 hover:scale-105 active:scale-98"
          >
            Start Free Trial — Instant Access
          </button>
          
          <div className="text-[11px] text-slate-500 uppercase tracking-widest mt-4">
            No credit card required
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="relative z-10 border-t border-white/5 bg-[#07070f] py-12">
        <div className="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6">
          <div className="flex items-center gap-2">
            <span className="font-serif text-2xl font-light tracking-wide text-[#c5a572]">Sol<span className="text-white">en</span></span>
          </div>

          <div className="flex items-center gap-8 text-xs text-slate-400">
            <span className="hover:text-white transition cursor-pointer">Privacy Policy</span>
            <span className="hover:text-white transition cursor-pointer">Terms of Service</span>
            <span className="hover:text-white transition cursor-pointer">Support</span>
          </div>

          <div className="text-[11px] text-slate-500">
            © {new Date().getFullYear()} Solen. All rights reserved.
          </div>
        </div>
      </footer>

    </div>
  );
}
