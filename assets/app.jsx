
const { useState, useEffect, useRef, useCallback } = React;

const THEMES = {
  emotional: { bg:"#07070f", surface:"#0d0d18", accent:"#a78bfa", soft:"#2d1454", mid:"#1a0a2e", glow:"rgba(167,139,250,0.15)" },
  anxiety:   { bg:"#060f0c", surface:"#0a1a14", accent:"#34d399", soft:"#0a2e1e", mid:"#071a10", glow:"rgba(52,211,153,0.15)" },
  growth:    { bg:"#0a0800", surface:"#141000", accent:"#f59e0b", soft:"#2e1f00", mid:"#1a1200", glow:"rgba(245,158,11,0.15)" },
  social:    { bg:"#060810", surface:"#0a0e1a", accent:"#60a5fa", soft:"#102040", mid:"#0a0f2e", glow:"rgba(96,165,250,0.15)" },
};

const STEPS = [
  { id:"purpose", question:"What brings you here today?", subtitle:"Your coach will be shaped around this intention.",
    options:[
      {id:"emotional",emoji:"🫂",label:"Emotional support",desc:"Someone who truly listens without judgment"},
      {id:"anxiety",  emoji:"🌿",label:"Anxiety & stress",  desc:"Calm, grounding support when life feels heavy"},
      {id:"growth",   emoji:"🌱",label:"Personal growth",   desc:"Reflect deeply, set goals, become your best self"},
      {id:"social",   emoji:"💬",label:"Social confidence", desc:"Build confidence and connection skills"},
    ]},
  { id:"tone", question:"How should your coach speak to you?", subtitle:"Choose what feels most like home.",
    options:[
      {id:"warm",   emoji:"☀️",label:"Warm & nurturing",desc:"Gentle, caring, never judgmental"},
      {id:"direct", emoji:"⚡",label:"Direct & honest",  desc:"Straight talk, no sugarcoating"},
      {id:"playful",emoji:"✨",label:"Light & playful",  desc:"Uplifting, even when things are hard"},
      {id:"wise",   emoji:"🌙",label:"Calm & wise",      desc:"Thoughtful, unhurried, deeply present"},
    ]},
  { id:"challenge", question:"What's your biggest challenge right now?", subtitle:"Be honest — this stays between you.",
    options:[
      {id:"overwhelm", emoji:"🌊",label:"Feeling overwhelmed",   desc:"Too much at once, no way to breathe"},
      {id:"loneliness",emoji:"🤍",label:"Loneliness or isolation",desc:"Disconnected from people I care about"},
      {id:"selfworth", emoji:"🪞",label:"Self-doubt",            desc:"Hard to believe in myself lately"},
      {id:"direction", emoji:"🧭",label:"Lack of direction",     desc:"Unsure what I want or where I'm going"},
    ]},
  { id:"name", question:"Give your coach a name.", subtitle:"Something that feels right to you — a name you'd actually say.", isNameStep:true },
];

const purposePrompts = {
  emotional:"You are a deeply empathetic emotional wellness coach. Listen actively, validate feelings, and help the user feel genuinely understood.",
  anxiety:"You are a calm, grounding anxiety and stress coach. Use CBT and mindfulness naturally.",
  growth:"You are an insightful personal growth coach. Ask powerful reflective questions and celebrate wins.",
  social:"You are a warm, patient social confidence coach. Help users build conversational skills.",
};
const toneStyles = {
  warm:"Your tone is warm, gentle, and deeply caring.",
  direct:"Your tone is honest, clear, and direct.",
  playful:"Your tone is light and uplifting.",
  wise:"Your tone is calm and thoughtful.",
};
const challengeContext = {
  overwhelm:"The user is currently struggling with feeling overwhelmed.",
  loneliness:"The user is experiencing loneliness.",
  selfworth:"The user struggles with self-doubt.",
  direction:"The user lacks direction.",
};
const CRISIS_RE = /\b(suicid|kill myself|end my life|want to die)\b/i;
const MOODS = [
  {score:1,emoji:"😔",label:"Low",   color:"#ef4444"},
  {score:2,emoji:"😕",label:"Rough", color:"#f97316"},
  {score:3,emoji:"😐",label:"Okay",  color:"#eab308"},
  {score:4,emoji:"🙂",label:"Good",  color:"#84cc16"},
  {score:5,emoji:"😊",label:"Great", color:"#22c55e"},
];
const PROGRAMS = {
  emotional:{name:"Emotional Foundations",days:["Share one thing weighing on you today.","What emotion have you felt most this week?","Describe a moment recently when you felt okay.","What have you been carrying alone?","What would giving yourself permission to rest mean?","Name one person who makes you feel safe.","One small act of self-kindness?"]},
  anxiety:  {name:"Calm & Ground",days:["Take 3 slow breaths. What's on your mind?","What worry keeps returning?","Name 5 things you can see right now.","What story are you telling yourself?","What's in your control today?","Describe a moment anxiety showed up.","What does your body need?"]},
  growth:   {name:"Becoming",days:["Describe your ideal self in 6 months.","What belief is holding you back?","What are you proud of this week?","What goal excites and scares you?","Who do you admire?","What if you couldn't fail?","What habit shapes your days?"]},
  social:   {name:"Connection Lab",days:["Tell me about a recent social situation.","What social scenario makes you anxious?","Describe someone easy to talk to.","Introduce yourself as if we just met.","What do you wish people understood?","A conversation that didn't go well?","What social step could you take?"]},
};

const EMOTIONAL_TONE_MODS = {
  crisis:        "IMPORTANT: The user is showing signs of crisis. Lead with grounding. Do not offer solutions yet.",
  high_distress: "The user appears to be in significant emotional pain. Drop any agenda. Be fully present.",
  burnout:       "The user shows signs of burnout. Speak gently. Validate tiredness. Avoid pressure.",
  anxiety_high:  "The user's anxiety is elevated. Use a calm, slow pacing. Include grounding.",
  low:           "The user seems to be in a low emotional state. Be extra gentle, extra warm.",
};

function detectEmotion(text) {
  const t = text.toLowerCase();
  if (CRISIS_RE.test(t)) return {state:'crisis', score:1.0};
  const distressWords = ['hopeless','worthless','broken','can\'t cope','falling apart'];
  if (distressWords.some(w=>t.includes(w))) return {state:'high_distress', score:0.8};
  const anxietyWords = ['anxious','panicking','overwhelmed','can\'t breathe'];
  if (anxietyWords.some(w=>t.includes(w))) return {state:'anxiety_high', score:0.65};
  return {state:'neutral', score:0.3};
}

function buildSystem(prof, mem, emotionalState = '') {
  const base = purposePrompts[prof.purpose] || purposePrompts.emotional;
  const tone = toneStyles[prof.tone] || toneStyles.warm;
  const emotMod = emotionalState && EMOTIONAL_TONE_MODS[emotionalState] ? '\n\n' + EMOTIONAL_TONE_MODS[emotionalState] : '';
  const memB = mem.length ? "\n\nMEMORIES:\n" + mem.slice(-5).map(m=>`- ${m.summary}`).join("\n") : "";
  return `${base} ${tone}${emotMod}${memB}\n\nYour name is ${prof.coach_name}. Conversational, 2-4 sentences, empathetic.`;
}

async function apiData(action, body = {}) {
  const r = await fetch(`/api/data.php?action=${action}`, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return r.json();
}
async function streamClaude(payload, onChunk) {
  const provider = window.SOLEN_AI_PROVIDER || 'claude';
  const r = await fetch(`/api/ai.php`, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...payload, provider})});
  const reader = r.body.getReader(); const dec = new TextDecoder();
  let buf = "", full = "";
  while (true) {
    const {done,value} = await reader.read(); if (done) break;
    buf += dec.decode(value,{stream:true});
    const lines = buf.split("\n"); buf = lines.pop();
    for (const line of lines) {
      if (!line.startsWith("data: ")) continue;
      try { const ev = JSON.parse(line.slice(6)); if (ev.type==="content_block_delta") { full += ev.delta.text; onChunk(full); } } catch {}
    }
  }
  return full;
}

const EmotionalHeartbeat = ({ state, theme }) => {
  const canvasRef = useRef(null);
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let frame = 0;
    
    const config = {
      crisis:        { freq: 0.15, amp: 15, color: '#ef4444' },
      high_distress: { freq: 0.12, amp: 12, color: '#f87171' },
      burnout:       { freq: 0.04, amp: 4,  color: '#a78bfa' },
      anxiety_high:  { freq: 0.18, amp: 10, color: '#60a5fa' },
      low:           { freq: 0.05, amp: 6,  color: '#94a3b8' },
      neutral:       { freq: 0.08, amp: 8,  color: theme.accent },
      positive:      { freq: 0.10, amp: 10, color: '#34d399' },
    };
    const c = config[state] || config.neutral;

    const animate = () => {
      frame++;
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.beginPath();
      ctx.lineWidth = 2;
      ctx.strokeStyle = c.color;
      ctx.lineCap = 'round';
      
      const width = canvas.width;
      const height = canvas.height;
      const mid = height / 2;
      
      for (let x = 0; x < width; x++) {
        const y = mid + Math.sin(x * c.freq + frame * 0.1) * c.amp;
        if (x === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      }
      ctx.stroke();
      requestAnimationFrame(animate);
    };
    const handle = requestAnimationFrame(animate);
    return () => cancelAnimationFrame(handle);
  }, [state, theme]);

  return <canvas ref={canvasRef} width={120} height={40} style={{ opacity: 0.8 }} />;
};

function SolenApp() {
  const [step, setStep] = useState(0);
  const [answers, setAnswers] = useState({});
  const [nameIn, setNameIn] = useState("");
  const [phase, setPhase] = useState("loading");
  const [profile, setProfile] = useState(null);
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [stream, setStream] = useState("");
  const [memory, setMemory] = useState([]);
  const [moods, setMoods] = useState([]);
  const [activeTab, setTab] = useState("chat");
  const [progDay, setProgDay] = useState(0);
  const [emotState, setEmotState] = useState('neutral');
  const [intel, setIntel] = useState(null);
  const [loadingIntel, setLoadingIntel] = useState(false);
  
  const endRef = useRef(null);
  const theme = THEMES[profile?.purpose || answers.purpose || "emotional"];

  useEffect(() => {
    (async () => {
      // Fast path: load profile first, show UI immediately
      const pm = await apiData('get_profile');
      if (pm?.profile?.coach_name) {
        setProfile(pm.profile);
        setProgDay(pm.profile.program_day || 0);
        setPhase("chat");
        // Deferred: load remaining data after UI is visible
        const [mm, sm, md] = await Promise.all([
          apiData('get_memory'), apiData('get_session'), apiData('get_moods')
        ]);
        setMoods(md.moods || []);
        setMemory(mm.memory || []);
        if (sm?.messages?.length) setMessages(sm.messages);
        else setMessages([{role:"assistant",content:`Hey. I'm ${pm.profile.coach_name}. How are you today?`}]);
      } else {
        setPhase("onboarding");
      }
    })();
  }, []);

  useEffect(() => { endRef.current?.scrollIntoView({behavior:"smooth"}); }, [messages, stream]);

  async function send(override) {
    const text = (override || input).trim(); if (!text || loading) return;
    setInput(""); const emot = detectEmotion(text); setEmotState(emot.state);
    const history = [...messages, {role:"user",content:text}]; setMessages(history);
    setLoading(true); setStream("");
    const full = await streamClaude({ system: buildSystem(profile, memory, emot.state), messages: history.map(m=>({role:m.role,content:m.content})) }, s => setStream(s));
    setStream(""); const next = [...history, {role:"assistant",content:full}]; setMessages(next); setLoading(false);
    apiData("save_session", { messages: next });
    apiData("log_emotion", { state: emot.state, score: emot.score, source: 'chat' });
  }

  function selectOpt(id, val) { setAnswers(p=>({...p,[id]:val})); setTimeout(()=>setStep(s=>s+1), 300); }
  async function confirmName() {
    const prof = {...answers, coach_name:nameIn.trim(), program_day:0};
    setProfile(prof); await apiData("save_profile", prof);
    setPhase("reveal"); setTimeout(()=>setPhase("chat"), 2000);
  }

  if (phase==="loading") return <div style={{minHeight:"100vh",background:theme.bg,display:"flex",alignItems:"center",justifyContent:"center",color:theme.accent}}>Solen is waking up...</div>;
  if (phase==="reveal") return <div style={{minHeight:"100vh",background:theme.bg,display:"flex",flexDirection:"column",alignItems:"center",justifyContent:"center",gap:20,color:"#fff"}}>
    <div style={{width:80,height:80,borderRadius:"50%",background:`radial-gradient(circle at 35% 35%, ${theme.accent}, ${theme.soft})`}}/>
    <h1 style={{fontFamily:"'Playfair Display',serif",fontSize:32}}>{profile?.coach_name}</h1>
  </div>;

  if (phase==="onboarding") {
    const cur = STEPS[step];
    return (
      <div style={{minHeight:"100vh",background:theme.bg,display:"flex",flexDirection:"column",alignItems:"center",justifyContent:"center",padding:20,color:"#fff"}}>
        <div style={{width:"100%",maxWidth:480}}>
          <h2 style={{fontFamily:"'Playfair Display',serif",fontSize:24,marginBottom:8}}>{cur.question}</h2>
          <p style={{fontSize:14,opacity:0.4,marginBottom:32}}>{cur.subtitle}</p>
          {cur.isNameStep ? (
            <div style={{display:"flex",flexDirection:"column",gap:16}}>
              <input autoFocus value={nameIn} onChange={e=>setNameIn(e.target.value)} placeholder="Name your coach..." style={{background:"rgba(255,255,255,0.06)",border:"1px solid rgba(255,255,255,0.1)",borderRadius:16,padding:18,color:"#fff",fontSize:18}}/>
              <button onClick={confirmName} style={{background:theme.accent,color:theme.soft,padding:15,borderRadius:50,border:"none",fontWeight:600}}>Continue →</button>
            </div>
          ) : (
            <div style={{display:"flex",flexDirection:"column",gap:12}}>
              {cur.options.map(o=>(
                <button key={o.id} onClick={()=>selectOpt(cur.id,o.id)} style={{background:"rgba(255,255,255,0.04)",border:"1px solid rgba(255,255,255,0.08)",borderRadius:16,padding:16,textAlign:"left",color:"#fff",display:"flex",alignItems:"center",gap:12}}>
                  <span style={{fontSize:24}}>{o.emoji}</span>
                  <div><div style={{fontWeight:500}}>{o.label}</div><div style={{fontSize:12,opacity:0.4}}>{o.desc}</div></div>
                </button>
              ))}
            </div>
          )}
        </div>
      </div>
    );
  }

  const prog = PROGRAMS[profile.purpose] || PROGRAMS.emotional;

  return (
    <div style={{minHeight:"100vh",maxHeight:"100vh",background:theme.bg,display:"flex",flexDirection:"column",color:"#fff",overflow:"hidden"}}>
      <style>{`
        @keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
        .tab-btn{flex:1;padding:12px 0;border:none;background:transparent;color:rgba(255,255,255,0.3);font-size:11px;text-transform:uppercase;letter-spacing:0.1em;cursor:pointer}
        .msg-bubble{max-width:80%;padding:12px 16px;border-radius:18px;line-height:1.6;font-size:14.5px}
        .intel-card{background:rgba(255,255,255,0.03);padding:24px;border-radius:24px;border:1px solid rgba(255,255,255,0.06);margin-bottom:16px}
        .intel-tag{display:inline-block;padding:4px 12px;background:rgba(255,255,255,0.05);border-radius:50px;font-size:11px;color:var(--accent);margin-right:8px;margin-bottom:8px}
      `}</style>

      {/* Header */}
      <div style={{padding:"14px 16px",borderBottom:"1px solid rgba(255,255,255,0.06)",display:"flex",alignItems:"center",gap:12}}>
        <div style={{position:"relative"}}>
          <div style={{width:40,height:40,borderRadius:"50%",background:`radial-gradient(circle at 35% 35%, ${theme.accent}, ${theme.soft})`,boxShadow:emotState==='neutral'?`0 0 10px ${theme.accent}33`:`0 0 20px ${emotState==='anxiety_high'?'#60a5fa':'#f87171'}`,transition:"all 0.5s"}}/>
          <div style={{position:"absolute",bottom:-2,right:-2,width:12,height:12,borderRadius:"50%",background:emotState==='crisis'?'#ef4444':'#22c55e',border:"2px solid #07070f"}}/>
        </div>
        <div style={{flex:1}}>
          <div style={{fontFamily:"'Playfair Display',serif",fontSize:16}}>{profile?.coach_name}</div>
          <div style={{fontSize:10,opacity:0.3,textTransform:"uppercase"}}>{profile?.purpose} coach</div>
        </div>
        <div style={{display:"flex",alignItems:"center",gap:8}}>
          <EmotionalHeartbeat state={emotState} theme={theme} />
          <button onClick={()=>setTab("insights")} style={{background:"rgba(255,255,255,0.05)",border:"none",color:theme.accent,padding:"5px 12px",borderRadius:20,fontSize:11}}>📊 Insights</button>
        </div>
      </div>

      <div style={{display:"flex",borderBottom:"1px solid rgba(255,255,255,0.06)"}}>
        {["chat","program","history"].map(t=>(
          <button key={t} onClick={()=>setTab(t)} className="tab-btn" style={{color:activeTab===t?theme.accent:undefined,borderBottom:activeTab===t?`2px solid ${theme.accent}`:"2px solid transparent"}}>{t}</button>
        ))}
      </div>

      <div style={{flex:1,overflowY:"auto",display:"flex",flexDirection:"column"}}>
        {activeTab==="chat" && <div style={{padding:16,display:"flex",flexDirection:"column",gap:12}}>
          {messages.map((m,i)=>(
            <div key={i} style={{display:"flex",justifyContent:m.role==="user"?"flex-end":"flex-start",animation:"fadeUp 0.3s"}}>
              <div className="msg-bubble" style={{background:m.role==="user"?theme.soft:"rgba(255,255,255,0.06)",color:m.role==="user"?theme.accent:"#fff",border:m.role==="user"?`1px solid ${theme.accent}33`:"1px solid rgba(255,255,255,0.03)"}}>{m.content}</div>
            </div>
          ))}
          {stream && <div style={{display:"flex",justifyContent:"flex-start"}}><div className="msg-bubble" style={{background:"rgba(255,255,255,0.06)"}}>{stream}</div></div>}
          <div ref={endRef}/>
        </div>}
        
        {activeTab==="insights" && <div style={{padding:20,display:"flex",flexDirection:"column",gap:16}}>
          <div className="intel-card">
            <div style={{fontSize:10,opacity:0.4,textTransform:"uppercase",letterSpacing:"0.1em",marginBottom:16}}>Longitudinal Life Intelligence</div>
            {!intel && <button onClick={async ()=>{setLoadingIntel(true);const r=await apiData('get_life_intelligence');setIntel(r.intelligence);setLoadingIntel(false);}} style={{background:theme.accent,color:theme.soft,border:"none",padding:"12px 24px",borderRadius:50,fontSize:13,fontWeight:600,width:"100%"}}>Analyze My Journey →</button>}
            {loadingIntel && <div style={{padding:"20px 0",textAlign:"center",fontSize:13,opacity:0.5}}>Solen is reflecting on your path...</div>}
            {intel && <div style={{animation:"fadeUp 0.5s"}}>
              <h3 style={{fontSize:22,fontFamily:"'Playfair Display',serif",color:theme.accent,marginBottom:12}}>{intel.life_phase}</h3>
              <p style={{fontSize:14,opacity:0.7,lineHeight:1.7,marginBottom:20}}>{intel.evolution}</p>
              <div style={{marginBottom:20}}>
                {intel.patterns?.map((p,i)=><span key={i} className="intel-tag">#{p}</span>)}
              </div>
              <div style={{padding:16,background:"rgba(184,149,106,0.05)",borderRadius:16,borderLeft:`3px solid ${theme.accent}`}}>
                <div style={{fontSize:11,opacity:0.5,marginBottom:4}}>Core Insight</div>
                <div style={{fontSize:14,fontStyle:"italic"}}>{intel.insight}</div>
              </div>
            </div>}
          </div>
          
          <div style={{fontSize:10,opacity:0.3,textAlign:"center"}}>Evolution snapshots update as you grow.</div>
        </div>}
        
        {activeTab==="program" && <div style={{padding:20}}>
           <div style={{background:`${theme.accent}0f`,padding:24,borderRadius:24,border:`1px solid ${theme.accent}33`}}>
             <div style={{fontSize:11,color:theme.accent,textTransform:"uppercase",marginBottom:8}}>Day {progDay+1}</div>
             <p style={{fontFamily:"'Playfair Display',serif",fontSize:20,lineHeight:1.5,marginBottom:24}}>"{prog.days[progDay%prog.days.length]}"</p>
             <button onClick={()=>{setTab("chat");send(prog.days[progDay%prog.days.length])}} style={{background:theme.accent,color:theme.soft,border:"none",padding:"12px 28px",borderRadius:50,fontSize:14,fontWeight:600,width:"100%"}}>Start Today's Session →</button>
           </div>
        </div>}
      </div>

      {activeTab==="chat" && <div style={{padding:12,paddingBottom:"max(12px, env(safe-area-inset-bottom))",borderTop:"1px solid rgba(255,255,255,0.06)",display:"flex",gap:10,background:theme.bg}}>
        <textarea rows={1} value={input} onChange={e=>setInput(e.target.value)} onKeyDown={e=>{if(e.key==="Enter"&&!e.shiftKey){e.preventDefault();send();}}} placeholder="Type your heart out..." style={{flex:1,background:"rgba(255,255,255,0.05)",border:"none",padding:12,color:"#fff",resize:"none",borderRadius:16,fontSize:15}}/>
        <button onClick={()=>send()} style={{width:44,height:44,borderRadius:"50%",border:"none",background:theme.accent,color:theme.soft,fontSize:18,display:"flex",alignItems:"center",justifyContent:"center"}}>↑</button>
      </div>}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<SolenApp/>);

