import React, { useState, useEffect, useRef } from 'react';

interface Message { role: 'user' | 'assistant'; content: string; }

const MOODS = [
  { emoji: '😊', label: 'Great', score: 9 },
  { emoji: '🙂', label: 'Good', score: 7 },
  { emoji: '😐', label: 'Okay', score: 5 },
  { emoji: '😔', label: 'Low', score: 3 },
  { emoji: '😢', label: 'Rough', score: 1 },
];

export default function Chat() {
  const [messages, setMessages] = useState<Message[]>([
    { role: 'assistant', content: "Hi there 💙 I'm Solen, your personal wellness coach. How are you feeling today?" }
  ]);
  const [input, setInput] = useState('');
  const [streaming, setStreaming] = useState(false);
  const [selectedMood, setSelectedMood] = useState<number | null>(null);
  const endRef = useRef<HTMLDivElement>(null);

  // Microphone / Whisper recording states
  const [recording, setRecording] = useState(false);
  const [transcribing, setTranscribing] = useState(false);
  const [mediaRecorder, setMediaRecorder] = useState<MediaRecorder | null>(null);

  useEffect(() => { endRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [messages]);

  const startRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const recorder = new MediaRecorder(stream);
      const chunks: Blob[] = [];

      recorder.ondataavailable = (e) => {
        if (e.data.size > 0) chunks.push(e.data);
      };

      recorder.onstop = async () => {
        const audioBlob = new Blob(chunks, { type: 'audio/webm' });
        stream.getTracks().forEach(t => t.stop()); // release mic
        await transcribeAudio(audioBlob);
      };

      recorder.start();
      setMediaRecorder(recorder);
      setRecording(true);
    } catch (err) {
      console.error('Failed to access microphone:', err);
      alert('Microphone access is required for voice coaching.');
    }
  };

  const stopRecording = () => {
    if (mediaRecorder && recording) {
      mediaRecorder.stop();
      setRecording(false);
    }
  };

  const transcribeAudio = async (audioBlob: Blob) => {
    setTranscribing(true);
    try {
      const res = await fetch('/api/ai/transcribe', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'audio/webm' },
        body: audioBlob
      });
      const data = await res.json();
      if (data.text) {
        setInput(prev => (prev ? prev + ' ' : '') + data.text);
      }
    } catch (err) {
      console.error('Audio transcription failed:', err);
    } finally {
      setTranscribing(false);
    }
  };

  const send = async () => {
    const text = input.trim();
    if (!text || streaming) return;
    setInput('');

    const newMessages: Message[] = [...messages, { role: 'user', content: text }];
    setMessages(newMessages);
    setStreaming(true);

    // Add empty AI message for streaming
    setMessages(prev => [...prev, { role: 'assistant', content: '' }]);

    try {
      const mood = selectedMood !== null ? MOODS[selectedMood] : null;
      const res = await fetch('/api/chat', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          messages: newMessages.map(m => ({ role: m.role, content: m.content })),
          moodContext: mood ? `${mood.emoji} ${mood.label} (${mood.score}/10)` : null,
        }),
      });

      if (!res.body) throw new Error('No stream');
      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let full = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        const chunk = decoder.decode(value, { stream: true });
        // Parse SSE lines
        for (const line of chunk.split('\n')) {
          if (line.startsWith('data: ') && line !== 'data: [DONE]') {
            try {
              const json = JSON.parse(line.slice(6));
              full += json.response ?? '';
              setMessages(prev => {
                const updated = [...prev];
                updated[updated.length - 1] = { role: 'assistant', content: full };
                return updated;
              });
            } catch { full += line.slice(6); }
          }
        }
      }
    } catch (err) {
      setMessages(prev => {
        const updated = [...prev];
        updated[updated.length - 1] = { role: 'assistant', content: 'Something went wrong. Please try again.' };
        return updated;
      });
    } finally { setStreaming(false); }
  };

  return (
    <div className="flex flex-col h-full bg-slate-950">
      {/* Mood Bar */}
      <div className="flex items-center gap-2 px-4 py-3 bg-slate-900 border-b border-slate-800">
        <span className="text-xs text-slate-500 mr-1">Mood:</span>
        {MOODS.map((m, i) => (
          <button key={i} onClick={() => setSelectedMood(i === selectedMood ? null : i)}
            title={m.label}
            className={`text-lg transition-all duration-150 ${i === selectedMood ? 'scale-125' : 'opacity-50 hover:opacity-100'}`}>
            {m.emoji}
          </button>
        ))}
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-4 py-6 space-y-4">
        {messages.map((m, i) => (
          <div key={i} className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
            {m.role === 'assistant' && (
              <div className="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-violet-500 flex items-center justify-center text-xs mr-2 shrink-0 mt-1">S</div>
            )}
            <div className={`max-w-[78%] px-4 py-3 rounded-2xl text-sm leading-relaxed whitespace-pre-wrap
              ${m.role === 'user'
                ? 'bg-blue-600 text-white rounded-br-sm'
                : 'bg-slate-800 text-slate-100 rounded-bl-sm'}`}>
              {m.content || (streaming && i === messages.length - 1 ? (
                <span className="flex gap-1 items-center h-4">
                  {[0,1,2].map(d => <span key={d} className="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style={{ animationDelay: `${d*150}ms` }} />)}
                </span>
              ) : '')}
            </div>
          </div>
        ))}
        <div ref={endRef} />
      </div>

      {/* Input */}
      <div className="px-4 py-4 bg-slate-900 border-t border-slate-800">
        <div className="flex gap-2 items-end">
          <textarea
            rows={1} value={input} onChange={e => setInput(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } }}
            placeholder="Share what's on your mind…"
            className="flex-1 bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 transition resize-none text-sm animate-fade-in"
          />
          <button
            onClick={recording ? stopRecording : startRecording}
            disabled={streaming || transcribing}
            className={`p-3 rounded-xl transition shrink-0 flex items-center justify-center border
              ${recording 
                ? 'bg-rose-600 hover:bg-rose-500 border-rose-500 text-white animate-pulse shadow-lg shadow-rose-600/30' 
                : transcribing 
                  ? 'bg-slate-700 border-slate-600 text-slate-400 cursor-not-allowed'
                  : 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-300'}`}
            title={recording ? "Stop Recording" : transcribing ? "Transcribing voice..." : "Record Voice Note"}
          >
            {transcribing ? (
              <div className="w-5 h-5 border-2 border-slate-500 border-t-[#c5a572] rounded-full animate-spin" />
            ) : (
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
              </svg>
            )}
          </button>
          <button onClick={send} disabled={streaming || !input.trim()}
            className="bg-blue-600 hover:bg-blue-500 disabled:opacity-40 text-white p-3 rounded-xl transition shrink-0">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
          </button>

        </div>
      </div>
    </div>
  );
}
