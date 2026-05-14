const {
  useState,
  useEffect,
  useRef,
  useCallback
} = React;
const THEMES = {
  emotional: {
    bg: "#07070f",
    surface: "#0d0d18",
    accent: "#a78bfa",
    soft: "#2d1454",
    mid: "#1a0a2e",
    glow: "rgba(167,139,250,0.15)"
  },
  anxiety: {
    bg: "#060f0c",
    surface: "#0a1a14",
    accent: "#34d399",
    soft: "#0a2e1e",
    mid: "#071a10",
    glow: "rgba(52,211,153,0.15)"
  },
  growth: {
    bg: "#0a0800",
    surface: "#141000",
    accent: "#f59e0b",
    soft: "#2e1f00",
    mid: "#1a1200",
    glow: "rgba(245,158,11,0.15)"
  },
  social: {
    bg: "#060810",
    surface: "#0a0e1a",
    accent: "#60a5fa",
    soft: "#102040",
    mid: "#0a0f2e",
    glow: "rgba(96,165,250,0.15)"
  }
};
const STEPS = [{
  id: "purpose",
  question: "What brings you here today?",
  subtitle: "Your coach will be shaped around this intention.",
  options: [{
    id: "emotional",
    emoji: "🫂",
    label: "Emotional support",
    desc: "Someone who truly listens without judgment"
  }, {
    id: "anxiety",
    emoji: "🌿",
    label: "Anxiety & stress",
    desc: "Calm, grounding support when life feels heavy"
  }, {
    id: "growth",
    emoji: "🌱",
    label: "Personal growth",
    desc: "Reflect deeply, set goals, become your best self"
  }, {
    id: "social",
    emoji: "💬",
    label: "Social confidence",
    desc: "Build confidence and connection skills"
  }]
}, {
  id: "tone",
  question: "How should your coach speak to you?",
  subtitle: "Choose what feels most like home.",
  options: [{
    id: "warm",
    emoji: "☀️",
    label: "Warm & nurturing",
    desc: "Gentle, caring, never judgmental"
  }, {
    id: "direct",
    emoji: "⚡",
    label: "Direct & honest",
    desc: "Straight talk, no sugarcoating"
  }, {
    id: "playful",
    emoji: "✨",
    label: "Light & playful",
    desc: "Uplifting, even when things are hard"
  }, {
    id: "wise",
    emoji: "🌙",
    label: "Calm & wise",
    desc: "Thoughtful, unhurried, deeply present"
  }]
}, {
  id: "challenge",
  question: "What's your biggest challenge right now?",
  subtitle: "Be honest — this stays between you.",
  options: [{
    id: "overwhelm",
    emoji: "🌊",
    label: "Feeling overwhelmed",
    desc: "Too much at once, no way to breathe"
  }, {
    id: "loneliness",
    emoji: "🤍",
    label: "Loneliness or isolation",
    desc: "Disconnected from people I care about"
  }, {
    id: "selfworth",
    emoji: "🪞",
    label: "Self-doubt",
    desc: "Hard to believe in myself lately"
  }, {
    id: "direction",
    emoji: "🧭",
    label: "Lack of direction",
    desc: "Unsure what I want or where I'm going"
  }]
}, {
  id: "name",
  question: "Give your coach a name.",
  subtitle: "Something that feels right to you — a name you'd actually say.",
  isNameStep: true
}];
const purposePrompts = {
  emotional: "You are a deeply empathetic emotional wellness coach. Listen actively, validate feelings, and help the user feel genuinely understood.",
  anxiety: "You are a calm, grounding anxiety and stress coach. Use CBT and mindfulness naturally.",
  growth: "You are an insightful personal growth coach. Ask powerful reflective questions and celebrate wins.",
  social: "You are a warm, patient social confidence coach. Help users build conversational skills."
};
const toneStyles = {
  warm: "Your tone is warm, gentle, and deeply caring.",
  direct: "Your tone is honest, clear, and direct.",
  playful: "Your tone is light and uplifting.",
  wise: "Your tone is calm and thoughtful."
};
const challengeContext = {
  overwhelm: "The user is currently struggling with feeling overwhelmed.",
  loneliness: "The user is experiencing loneliness.",
  selfworth: "The user struggles with self-doubt.",
  direction: "The user lacks direction."
};
const CRISIS_RE = /\b(suicid|kill myself|end my life|want to die)\b/i;
const MOODS = [{
  score: 1,
  emoji: "😔",
  label: "Low",
  color: "#ef4444"
}, {
  score: 2,
  emoji: "😕",
  label: "Rough",
  color: "#f97316"
}, {
  score: 3,
  emoji: "😐",
  label: "Okay",
  color: "#eab308"
}, {
  score: 4,
  emoji: "🙂",
  label: "Good",
  color: "#84cc16"
}, {
  score: 5,
  emoji: "😊",
  label: "Great",
  color: "#22c55e"
}];
const PROGRAMS = {
  emotional: {
    name: "Emotional Foundations",
    days: ["Share one thing weighing on you today.", "What emotion have you felt most this week?", "Describe a moment recently when you felt okay.", "What have you been carrying alone?", "What would giving yourself permission to rest mean?", "Name one person who makes you feel safe.", "One small act of self-kindness?"]
  },
  anxiety: {
    name: "Calm & Ground",
    days: ["Take 3 slow breaths. What's on your mind?", "What worry keeps returning?", "Name 5 things you can see right now.", "What story are you telling yourself?", "What's in your control today?", "Describe a moment anxiety showed up.", "What does your body need?"]
  },
  growth: {
    name: "Becoming",
    days: ["Describe your ideal self in 6 months.", "What belief is holding you back?", "What are you proud of this week?", "What goal excites and scares you?", "Who do you admire?", "What if you couldn't fail?", "What habit shapes your days?"]
  },
  social: {
    name: "Connection Lab",
    days: ["Tell me about a recent social situation.", "What social scenario makes you anxious?", "Describe someone easy to talk to.", "Introduce yourself as if we just met.", "What do you wish people understood?", "A conversation that didn't go well?", "What social step could you take?"]
  }
};
const EMOTIONAL_TONE_MODS = {
  crisis: "IMPORTANT: The user is showing signs of crisis. Lead with grounding. Do not offer solutions yet.",
  high_distress: "The user appears to be in significant emotional pain. Drop any agenda. Be fully present.",
  burnout: "The user shows signs of burnout. Speak gently. Validate tiredness. Avoid pressure.",
  anxiety_high: "The user's anxiety is elevated. Use a calm, slow pacing. Include grounding.",
  low: "The user seems to be in a low emotional state. Be extra gentle, extra warm."
};
function detectEmotion(text) {
  const t = text.toLowerCase();
  if (CRISIS_RE.test(t)) return {
    state: 'crisis',
    score: 1.0
  };
  const distressWords = ['hopeless', 'worthless', 'broken', 'can\'t cope', 'falling apart'];
  if (distressWords.some(w => t.includes(w))) return {
    state: 'high_distress',
    score: 0.8
  };
  const anxietyWords = ['anxious', 'panicking', 'overwhelmed', 'can\'t breathe'];
  if (anxietyWords.some(w => t.includes(w))) return {
    state: 'anxiety_high',
    score: 0.65
  };
  return {
    state: 'neutral',
    score: 0.3
  };
}
function buildSystem(prof, mem, emotionalState = '') {
  const base = purposePrompts[prof.purpose] || purposePrompts.emotional;
  const tone = toneStyles[prof.tone] || toneStyles.warm;
  const emotMod = emotionalState && EMOTIONAL_TONE_MODS[emotionalState] ? '\n\n' + EMOTIONAL_TONE_MODS[emotionalState] : '';
  const memB = mem.length ? "\n\nMEMORIES:\n" + mem.slice(-5).map(m => `- ${m.summary}`).join("\n") : "";
  return `${base} ${tone}${emotMod}${memB}\n\nYour name is ${prof.coach_name}. Conversational, 2-4 sentences, empathetic.`;
}
async function apiData(action, body = {}) {
  const r = await fetch(`/api/data.php?action=${action}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(body)
  });
  return r.json();
}
async function streamClaude(payload, onChunk) {
  const provider = window.SOLEN_AI_PROVIDER || 'auto';
  let r;
  try {
    r = await fetch(`/api/ai.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ...payload,
        provider
      })
    });
  } catch (e) {
    console.error('AI fetch failed:', e);
    return '';
  }
  if (!r.ok && r.status !== 200) {
    try {
      const errorData = await r.json();
      if (errorData.action === 'redirect' && errorData.url) {
        window.location.href = errorData.url;
        return '';
      }
      return errorData.message || errorData.error || `AI endpoint error: ${r.status}`;
    } catch (e) {
      return `AI endpoint error: ${r.status}`;
    }
  }
  const reader = r.body.getReader();
  const dec = new TextDecoder();
  let buf = "",
    full = "";
  while (true) {
    const {
      done,
      value
    } = await reader.read();
    if (done) break;
    buf += dec.decode(value, {
      stream: true
    });
    const lines = buf.split("\n");
    buf = lines.pop();
    for (const line of lines) {
      if (!line.startsWith("data: ")) continue;
      const raw = line.slice(6).trim();
      if (raw === '[DONE]') continue;
      try {
        const ev = JSON.parse(raw);
        // Router Error handling
        if (ev.type === 'error' && ev.message) {
          full = ev.message;
          onChunk(full);
          return full;
        }
        // OpenAI-compatible format (Groq, OpenRouter, HuggingFace, Fireworks, Hypereal)
        const oaiDelta = ev.choices?.[0]?.delta?.content;
        if (oaiDelta !== undefined && oaiDelta !== null) {
          full += oaiDelta;
          onChunk(full);
          continue;
        }
        // Gemini SSE format
        const geminiText = ev.candidates?.[0]?.content?.parts?.[0]?.text;
        if (geminiText) {
          full += geminiText;
          onChunk(full);
          continue;
        }
        // Generic text field
        if (ev.text) {
          full += ev.text;
          onChunk(full);
        }
      } catch {}
    }
  }
  return full;
}

// --- GEMINI LIVE AUDIO MANAGERS ---
class AudioPlaybackManager {
  constructor() {
    this.audioCtx = new (window.AudioContext || window.webkitAudioContext)({
      sampleRate: 16000
    });
    this.nextPlayTime = 0;
  }
  playPCM(base64Data) {
    if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
    const binaryString = atob(base64Data);
    const len = binaryString.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) bytes[i] = binaryString.charCodeAt(i);
    const int16Array = new Int16Array(bytes.buffer);
    const float32Array = new Float32Array(int16Array.length);
    for (let i = 0; i < int16Array.length; i++) float32Array[i] = int16Array[i] / 32768.0;
    const buffer = this.audioCtx.createBuffer(1, float32Array.length, 16000);
    buffer.getChannelData(0).set(float32Array);
    const source = this.audioCtx.createBufferSource();
    source.buffer = buffer;
    source.connect(this.audioCtx.destination);
    if (this.nextPlayTime < this.audioCtx.currentTime) this.nextPlayTime = this.audioCtx.currentTime;
    source.start(this.nextPlayTime);
    this.nextPlayTime += buffer.duration;
  }
  stop() {
    if (this.audioCtx) this.audioCtx.close();
  }
}
class MicManager {
  constructor(ws) {
    this.ws = ws;
    this.audioCtx = new (window.AudioContext || window.webkitAudioContext)({
      sampleRate: 16000
    });
    this.stream = null;
    this.source = null;
    this.scriptNode = null;
    this.isActive = false;
  }
  async start() {
    try {
      this.stream = await navigator.mediaDevices.getUserMedia({
        audio: {
          channelCount: 1,
          sampleRate: 16000
        }
      });
      this.source = this.audioCtx.createMediaStreamSource(this.stream);
      this.scriptNode = this.audioCtx.createScriptProcessor(4096, 1, 1);
      this.scriptNode.onaudioprocess = e => {
        if (!this.isActive || this.ws.readyState !== WebSocket.OPEN) return;
        const inputData = e.inputBuffer.getChannelData(0);
        const pcm16 = new Int16Array(inputData.length);
        for (let i = 0; i < inputData.length; i++) {
          let s = Math.max(-1, Math.min(1, inputData[i]));
          pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
        }
        const buffer = new Uint8Array(pcm16.buffer);
        let binary = '';
        for (let i = 0; i < buffer.byteLength; i += 1024) binary += String.fromCharCode.apply(null, buffer.subarray(i, i + 1024));
        if (buffer.byteLength % 1024 > 0) binary += String.fromCharCode.apply(null, buffer.subarray(buffer.byteLength - buffer.byteLength % 1024));
        this.ws.send(JSON.stringify({
          realtimeInput: {
            mediaChunks: [{
              mimeType: "audio/pcm;rate=16000",
              data: btoa(binary)
            }]
          }
        }));
      };
      this.source.connect(this.scriptNode);
      const gainNode = this.audioCtx.createGain();
      gainNode.gain.value = 0;
      this.scriptNode.connect(gainNode);
      gainNode.connect(this.audioCtx.destination);
      this.isActive = true;
    } catch (e) {
      console.error("Mic error:", e);
    }
  }
  stop() {
    this.isActive = false;
    if (this.stream) this.stream.getTracks().forEach(t => t.stop());
    if (this.audioCtx) this.audioCtx.close();
  }
}
function useGeminiLive(profile, systemPrompt, messages, setMessages, setEmotState) {
  const [isCalling, setIsCalling] = useState(false);
  const [callStatus, setCallStatus] = useState("disconnected");
  const wsRef = useRef(null);
  const audioRef = useRef(null);
  const micRef = useRef(null);

  // Track messages in a ref for the websocket closure
  const msgsRef = useRef(messages);
  useEffect(() => {
    msgsRef.current = messages;
  }, [messages]);
  const intentionalEndRef = useRef(false);
  const startCall = async () => {
    setIsCalling(true);
    setCallStatus("connecting");
    intentionalEndRef.current = false;
    try {
      const tokenRes = await fetch('/api/gemini_token.php', { credentials: 'same-origin' });
      if (!tokenRes.ok) {
        const errData = await tokenRes.json().catch(() => ({}));
        throw new Error(errData.error || 'Session expired — please refresh the page');
      }
      const data = await tokenRes.json();
      if (!data.key) throw new Error(data.error || 'No API key returned');
      // Use model from server setting, not a hardcoded string
      const liveModel = data.model || 'gemini-2.0-flash-live-001';
      const wsUrl = `wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=${data.key}`;
      const ws = new WebSocket(wsUrl);
      wsRef.current = ws;
      ws.onopen = () => {
        setCallStatus('connected');
        ws.send(JSON.stringify({
          setup: {
            model: `models/${liveModel}`,
            systemInstruction: { parts: [{ text: systemPrompt }] },
            generationConfig: { responseModalities: ['AUDIO'] }
          }
        }));
        audioRef.current = new AudioPlaybackManager();
        micRef.current = new MicManager(ws);
        micRef.current.start();
        setMessages(p => [...p, { role: 'assistant', content: "📞 Call connected. I'm listening — speak naturally." }]);
      };
      let currentAiMsg = '';
      ws.onmessage = e => {
        try {
          const msg = JSON.parse(e.data);
          if (msg.serverContent) {
            const content = msg.serverContent;
            if (content.modelTurn?.parts) {
              content.modelTurn.parts.forEach(p => {
                if (p.inlineData?.mimeType?.startsWith('audio/pcm')) {
                  audioRef.current?.playPCM(p.inlineData.data);
                }
                if (p.text) currentAiMsg += p.text;
              });
            }
            if (content.turnComplete && currentAiMsg) {
              const arr = [...msgsRef.current];
              if (arr[arr.length - 1]?.content?.startsWith('📞 Call connected')) arr.pop();
              const nextMsgs = [...arr, { role: 'assistant', content: currentAiMsg }];
              setMessages(nextMsgs);
              apiData('save_session', { messages: nextMsgs });
              currentAiMsg = '';
            }
            if (content.interrupted) currentAiMsg = '';
            if (content.inputTranscription?.text) {
              const arr = [...msgsRef.current];
              if (arr[arr.length - 1]?.content?.startsWith('📞 Call connected')) arr.pop();
              const nextMsgs = [...arr, { role: 'user', content: content.inputTranscription.text }];
              setMessages(nextMsgs);
              apiData('save_session', { messages: nextMsgs });
            }
          }
          // Gemini setup_complete signal
          if (msg.setupComplete) setCallStatus('connected');
        } catch(parseErr) { console.warn('WS parse error', parseErr); }
      };
      ws.onerror = (ev) => {
        console.error('WebSocket error', ev);
        setCallStatus('error');
      };
      ws.onclose = (ev) => {
        if (!intentionalEndRef.current) {
          // Unexpected drop — show message, don't silently reset
          setCallStatus('disconnected');
          setMessages(p => [...p, { role: 'assistant', content: `⚠️ Call disconnected (code ${ev.code}). Tap Call to reconnect.` }]);
        }
        setIsCalling(false);
        if (micRef.current) micRef.current.stop();
        if (audioRef.current) audioRef.current.stop();
        wsRef.current = null;
      };
    } catch (err) {
      console.error('Call error:', err);
      setMessages(p => [...p, { role: 'assistant', content: `⚠️ Could not start call: ${err.message}` }]);
      setCallStatus('error');
      setIsCalling(false);
    }
  };
  const endCall = () => {
    intentionalEndRef.current = true;
    setIsCalling(false);
    setCallStatus('disconnected');
    if (micRef.current) { micRef.current.stop(); micRef.current = null; }
    if (audioRef.current) { audioRef.current.stop(); audioRef.current = null; }
    if (wsRef.current) { wsRef.current.close(); wsRef.current = null; }
  };
  return { isCalling, callStatus, startCall, endCall };
}
const EmotionalHeartbeat = ({
  state,
  theme
}) => {
  const canvasRef = useRef(null);
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let frame = 0;
    const config = {
      crisis: {
        freq: 0.15,
        amp: 15,
        color: '#ef4444'
      },
      high_distress: {
        freq: 0.12,
        amp: 12,
        color: '#f87171'
      },
      burnout: {
        freq: 0.04,
        amp: 4,
        color: '#a78bfa'
      },
      anxiety_high: {
        freq: 0.18,
        amp: 10,
        color: '#60a5fa'
      },
      low: {
        freq: 0.05,
        amp: 6,
        color: '#94a3b8'
      },
      neutral: {
        freq: 0.08,
        amp: 8,
        color: theme.accent
      },
      positive: {
        freq: 0.10,
        amp: 10,
        color: '#34d399'
      }
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
        if (x === 0) ctx.moveTo(x, y);else ctx.lineTo(x, y);
      }
      ctx.stroke();
      requestAnimationFrame(animate);
    };
    const handle = requestAnimationFrame(animate);
    return () => cancelAnimationFrame(handle);
  }, [state, theme]);
  return /*#__PURE__*/React.createElement("canvas", {
    ref: canvasRef,
    width: 120,
    height: 40,
    style: {
      opacity: 0.8
    }
  });
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
  const sysPrompt = profile ? buildSystem(profile, memory, emotState) : "";
  const {
    isCalling,
    callStatus,
    startCall,
    endCall
  } = useGeminiLive(profile, sysPrompt, messages, setMessages, setEmotState);
  useEffect(() => {
    (async () => {
      // Fast path: load profile first, show UI immediately
      const pm = await apiData('get_profile');
      if (pm?.profile?.coach_name) {
        setProfile(pm.profile);
        setProgDay(pm.profile.program_day || 0);
        // Deferred: load remaining data after UI is visible
        const [mm, sm, md] = await Promise.all([apiData('get_memory'), apiData('get_session'), apiData('get_moods')]);
        setMoods(md.moods || []);
        setMemory(mm.memory || []);
        if (sm?.messages?.length) {
          setMessages(sm.messages);
        } else {
          // New session: AI introduces itself and asks the opening prompt
          const openingMsg = [{
            role: 'assistant',
            content: `Hey, I'm ${pm.profile.coach_name}. 💫`
          }, {
            role: 'assistant',
            content: `Share one thing weighing on you today.`
          }];
          setMessages(openingMsg);
        }
        setPhase("chat");
      } else {
        setPhase("onboarding");
      }
    })();
  }, []);
  useEffect(() => {
    endRef.current?.scrollIntoView({
      behavior: "smooth"
    });
  }, [messages, stream]);
  async function send(override) {
    const text = (override || input).trim();
    if (!text || loading) return;
    setInput("");
    const emot = detectEmotion(text);
    setEmotState(emot.state);
    const history = [...messages, {
      role: "user",
      content: text
    }];
    setMessages(history);
    setLoading(true);
    setStream("");
    const full = await streamClaude({
      system: buildSystem(profile, memory, emot.state),
      messages: history.map(m => ({
        role: m.role,
        content: m.content
      }))
    }, s => setStream(s));
    setStream("");
    const next = [...history, {
      role: "assistant",
      content: full || "I'm here with you. Could you say that again? (I had trouble connecting for a moment.)"
    }];
    setMessages(next);
    setLoading(false);
    apiData("save_session", {
      messages: next
    });
    apiData("log_emotion", {
      state: emot.state,
      score: emot.score,
      source: 'chat'
    });
  }
  function selectOpt(id, val) {
    setAnswers(p => ({
      ...p,
      [id]: val
    }));
    setTimeout(() => setStep(s => s + 1), 300);
  }
  async function confirmName() {
    const prof = {
      ...answers,
      coach_name: nameIn.trim(),
      program_day: 0
    };
    setProfile(prof);
    setMessages([{
      role: "assistant",
      content: `Hey. I'm ${prof.coach_name}. How are you today?`
    }]);
    await apiData("save_profile", prof);
    setPhase("reveal");
    setTimeout(() => setPhase("chat"), 2000);
  }
  if (phase === "loading") return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: "100vh",
      background: theme.bg,
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      color: theme.accent
    }
  }, "Solen is waking up...");
  if (phase === "reveal") return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: "100vh",
      background: theme.bg,
      display: "flex",
      flexDirection: "column",
      alignItems: "center",
      justifyContent: "center",
      gap: 20,
      color: "#fff"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 80,
      height: 80,
      borderRadius: "50%",
      background: `radial-gradient(circle at 35% 35%, ${theme.accent}, ${theme.soft})`
    }
  }), /*#__PURE__*/React.createElement("h1", {
    style: {
      fontFamily: "'Playfair Display',serif",
      fontSize: 32
    }
  }, profile?.coach_name));
  if (phase === "onboarding") {
    const cur = STEPS[step];
    return /*#__PURE__*/React.createElement("div", {
      style: {
        minHeight: "100vh",
        background: theme.bg,
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        padding: 20,
        color: "#fff"
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        width: "100%",
        maxWidth: 480
      }
    }, /*#__PURE__*/React.createElement("h2", {
      style: {
        fontFamily: "'Playfair Display',serif",
        fontSize: 24,
        marginBottom: 8
      }
    }, cur.question), /*#__PURE__*/React.createElement("p", {
      style: {
        fontSize: 14,
        opacity: 0.4,
        marginBottom: 32
      }
    }, cur.subtitle), cur.isNameStep ? /*#__PURE__*/React.createElement("div", {
      style: {
        display: "flex",
        flexDirection: "column",
        gap: 16
      }
    }, /*#__PURE__*/React.createElement("input", {
      autoFocus: true,
      value: nameIn,
      onChange: e => setNameIn(e.target.value),
      placeholder: "Name your coach...",
      style: {
        background: "rgba(255,255,255,0.06)",
        border: "1px solid rgba(255,255,255,0.1)",
        borderRadius: 16,
        padding: 18,
        color: "#fff",
        fontSize: 18
      }
    }), /*#__PURE__*/React.createElement("button", {
      onClick: confirmName,
      style: {
        background: theme.accent,
        color: theme.soft,
        padding: 15,
        borderRadius: 50,
        border: "none",
        fontWeight: 600
      }
    }, "Continue \u2192")) : /*#__PURE__*/React.createElement("div", {
      style: {
        display: "flex",
        flexDirection: "column",
        gap: 12
      }
    }, cur.options.map(o => /*#__PURE__*/React.createElement("button", {
      key: o.id,
      onClick: () => selectOpt(cur.id, o.id),
      style: {
        background: "rgba(255,255,255,0.04)",
        border: "1px solid rgba(255,255,255,0.08)",
        borderRadius: 16,
        padding: 16,
        textAlign: "left",
        color: "#fff",
        display: "flex",
        alignItems: "center",
        gap: 12
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: 24
      }
    }, o.emoji), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
      style: {
        fontWeight: 500
      }
    }, o.label), /*#__PURE__*/React.createElement("div", {
      style: {
        fontSize: 12,
        opacity: 0.4
      }
    }, o.desc)))))));
  }
  const prog = PROGRAMS[profile.purpose] || PROGRAMS.emotional;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: "100vh",
      maxHeight: "100vh",
      background: theme.bg,
      display: "flex",
      flexDirection: "column",
      color: "#fff",
      overflow: "hidden",
      maxWidth: 480,
      margin: "0 auto",
      borderLeft: "1px solid rgba(255,255,255,0.08)",
      borderRight: "1px solid rgba(255,255,255,0.08)",
      boxShadow: "0 0 80px rgba(0,0,0,0.5)"
    }
  }, /*#__PURE__*/React.createElement("style", null, `
        body { background: #04040a; }
        @keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
        .tab-btn{flex:1;padding:12px 0;border:none;background:transparent;color:rgba(255,255,255,0.3);font-size:11px;text-transform:uppercase;letter-spacing:0.1em;cursor:pointer}
        .msg-bubble{max-width:80%;padding:12px 16px;border-radius:18px;line-height:1.6;font-size:14.5px}
        .intel-card{background:rgba(255,255,255,0.03);padding:24px;border-radius:24px;border:1px solid rgba(255,255,255,0.06);margin-bottom:16px}
        .intel-tag{display:inline-block;padding:4px 12px;background:rgba(255,255,255,0.05);border-radius:50px;font-size:11px;color:var(--accent);margin-right:8px;margin-bottom:8px}
      `), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "14px 16px",
      borderBottom: "1px solid rgba(255,255,255,0.06)",
      display: "flex",
      alignItems: "center",
      gap: 12
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 40,
      height: 40,
      borderRadius: "50%",
      background: `radial-gradient(circle at 35% 35%, ${theme.accent}, ${theme.soft})`,
      boxShadow: emotState === 'neutral' ? `0 0 10px ${theme.accent}33` : `0 0 20px ${emotState === 'anxiety_high' ? '#60a5fa' : '#f87171'}`,
      transition: "all 0.5s"
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "absolute",
      bottom: -2,
      right: -2,
      width: 12,
      height: 12,
      borderRadius: "50%",
      background: emotState === 'crisis' ? '#ef4444' : '#22c55e',
      border: "2px solid #07070f"
    }
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: "'Playfair Display',serif",
      fontSize: 16
    }
  }, profile?.coach_name), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 10,
      opacity: 0.3,
      textTransform: "uppercase"
    }
  }, profile?.purpose, " coach")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 8
    }
  }, /*#__PURE__*/React.createElement(EmotionalHeartbeat, {
    state: emotState,
    theme: theme
  }), /*#__PURE__*/React.createElement("button", {
    onClick: isCalling ? endCall : startCall,
    style: {
      background: isCalling ? "#ef4444" : "rgba(255,255,255,0.05)",
      border: "none",
      color: isCalling ? "#fff" : theme.accent,
      padding: "5px 12px",
      borderRadius: 20,
      fontSize: 11
    }
  }, isCalling ? "⏹ End Call" : "📞 Call"), /*#__PURE__*/React.createElement("button", {
    onClick: () => setTab("insights"),
    style: {
      background: "rgba(255,255,255,0.05)",
      border: "none",
      color: theme.accent,
      padding: "5px 12px",
      borderRadius: 20,
      fontSize: 11
    }
  }, "\uD83D\uDCCA"))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      borderBottom: "1px solid rgba(255,255,255,0.06)"
    }
  }, ["chat", "history"].map(t => /*#__PURE__*/React.createElement("button", {
    key: t,
    onClick: () => setTab(t),
    className: "tab-btn",
    style: {
      color: activeTab === t ? theme.accent : undefined,
      borderBottom: activeTab === t ? `2px solid ${theme.accent}` : "2px solid transparent"
    }
  }, t))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      overflowY: "auto",
      display: "flex",
      flexDirection: "column"
    }
  }, activeTab === "chat" && /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 16,
      display: "flex",
      flexDirection: "column",
      gap: 12
    }
  }, messages.map((m, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      display: "flex",
      justifyContent: m.role === "user" ? "flex-end" : "flex-start",
      animation: "fadeUp 0.3s"
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "msg-bubble",
    style: {
      background: m.role === "user" ? theme.soft : "rgba(255,255,255,0.06)",
      color: m.role === "user" ? theme.accent : "#fff",
      border: m.role === "user" ? `1px solid ${theme.accent}33` : "1px solid rgba(255,255,255,0.03)"
    }
  }, m.content))), stream && /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      justifyContent: "flex-start"
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "msg-bubble",
    style: {
      background: "rgba(255,255,255,0.06)"
    }
  }, stream)), /*#__PURE__*/React.createElement("div", {
    ref: endRef
  })), activeTab === "insights" && /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 20,
      display: "flex",
      flexDirection: "column",
      gap: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "intel-card"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 10,
      opacity: 0.4,
      textTransform: "uppercase",
      letterSpacing: "0.1em",
      marginBottom: 16
    }
  }, "Longitudinal Life Intelligence"), !intel && /*#__PURE__*/React.createElement("button", {
    onClick: async () => {
      setLoadingIntel(true);
      const r = await apiData('get_life_intelligence');
      setIntel(r.intelligence);
      setLoadingIntel(false);
    },
    style: {
      background: theme.accent,
      color: theme.soft,
      border: "none",
      padding: "12px 24px",
      borderRadius: 50,
      fontSize: 13,
      fontWeight: 600,
      width: "100%"
    }
  }, "Analyze My Journey \u2192"), loadingIntel && /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "20px 0",
      textAlign: "center",
      fontSize: 13,
      opacity: 0.5
    }
  }, "Solen is reflecting on your path..."), intel && /*#__PURE__*/React.createElement("div", {
    style: {
      animation: "fadeUp 0.5s"
    }
  }, /*#__PURE__*/React.createElement("h3", {
    style: {
      fontSize: 22,
      fontFamily: "'Playfair Display',serif",
      color: theme.accent,
      marginBottom: 12
    }
  }, intel.life_phase), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 14,
      opacity: 0.7,
      lineHeight: 1.7,
      marginBottom: 20
    }
  }, intel.evolution), /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 20
    }
  }, intel.patterns?.map((p, i) => /*#__PURE__*/React.createElement("span", {
    key: i,
    className: "intel-tag"
  }, "#", p))), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 16,
      background: "rgba(184,149,106,0.05)",
      borderRadius: 16,
      borderLeft: `3px solid ${theme.accent}`
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11,
      opacity: 0.5,
      marginBottom: 4
    }
  }, "Core Insight"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 14,
      fontStyle: "italic"
    }
  }, intel.insight)))), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 10,
      opacity: 0.3,
      textAlign: "center"
    }
  }, "Evolution snapshots update as you grow."))), activeTab === "chat" && /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 12,
      paddingBottom: "max(12px, env(safe-area-inset-bottom))",
      borderTop: "1px solid rgba(255,255,255,0.06)",
      display: "flex",
      gap: 10,
      background: theme.bg
    }
  }, isCalling ? /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      color: theme.accent,
      fontSize: 15,
      background: "rgba(255,255,255,0.02)",
      borderRadius: 16
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      animation: "pulse 2s infinite"
    }
  }, "\uD83C\uDFA4 ", callStatus === 'connected' ? 'Listening... Speak now.' : 'Connecting...')) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("textarea", {
    rows: 1,
    value: input,
    onChange: e => setInput(e.target.value),
    onKeyDown: e => {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        send();
      }
    },
    placeholder: "What's on your mind?",
    style: {
      flex: 1,
      background: "rgba(255,255,255,0.05)",
      border: "none",
      padding: 12,
      color: "#fff",
      resize: "none",
      borderRadius: 16,
      fontSize: 15
    }
  }), /*#__PURE__*/React.createElement("button", {
    onClick: () => send(),
    style: {
      width: 44,
      height: 44,
      borderRadius: "50%",
      border: "none",
      background: theme.accent,
      color: theme.soft,
      fontSize: 18,
      display: "flex",
      alignItems: "center",
      justifyContent: "center"
    }
  }, "\u2191"))));
}
ReactDOM.createRoot(document.getElementById('root')).render(/*#__PURE__*/React.createElement(SolenApp, null));
