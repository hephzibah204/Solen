import React, { useEffect, useState } from 'react';

interface Quiz {
  id: number;
  question: string;
  options: string[];
}

interface Article {
  id: number;
  title: string;
  content: string;
  category: string;
  readTimeMin: number;
}

export default function StreakLearning({ onCompleted }: { onCompleted: () => void }) {
  const [loading, setLoading] = useState(true);
  const [article, setArticle] = useState<Article | null>(null);
  const [quizzes, setQuizzes] = useState<Quiz[]>([]);
  const [progress, setProgress] = useState<any>(null);
  
  const [view, setView] = useState<'card' | 'read' | 'quiz' | 'completed'>('card');
  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [quizResult, setQuizResult] = useState<any>(null);

  const fetchTodayArticle = async () => {
    try {
      const r = await fetch('/api/streak/today-article');
      const data = await r.json();
      setArticle(data.article);
      setQuizzes(data.quizzes || []);
      setProgress(data.progress);
      
      if (data.progress?.quiz_completed || data.progress?.quizCompleted) {
        setView('completed');
      }
    } catch {
      // Quiet fail if no articles are seeded
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTodayArticle();
  }, []);

  const handleMarkRead = async () => {
    if (!article) return;
    try {
      await fetch('/api/streak/read-article', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ article_id: article.id })
      });
      if (quizzes.length > 0) {
        setView('quiz');
      } else {
        setView('completed');
        onCompleted();
      }
    } catch {
      // Continue
    }
  };

  const handleSubmitQuiz = async () => {
    if (!article) return;
    try {
      const r = await fetch('/api/streak/submit-quiz', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ article_id: article.id, answers })
      });
      const data = await r.json();
      if (data.ok) {
        setQuizResult(data);
        setView('completed');
        onCompleted();
      }
    } catch {
      // Continue
    }
  };

  if (loading || !article) return null;

  return (
    <div className="bg-[#0e0e1a]/85 border border-white/5 rounded-3xl p-6 shadow-xl relative overflow-hidden">
      <div className="absolute -bottom-16 -right-16 w-32 h-32 bg-[#c5a572]/5 rounded-full blur-2xl" />

      {view === 'card' && (
        <div className="relative z-10">
          <span className="text-[10px] tracking-wider text-[#c5a572] font-bold uppercase">DAILY RETENTION PROGRAM</span>
          <h3 className="font-serif text-2xl font-light text-white mt-1 mb-2">{article.title}</h3>
          
          <div className="flex items-center gap-3 text-slate-400 text-xs mb-5">
            <span>📚 {article.category}</span>
            <span>·</span>
            <span>⏱️ {article.readTimeMin} min read</span>
          </div>

          <button 
            onClick={() => setView('read')}
            className="bg-[#c5a572] hover:bg-[#d9b884] text-[#1a1008] text-xs font-semibold px-5 py-2.5 rounded-xl transition hover:scale-[1.02] active:scale-98"
          >
            Start Daily Reflection
          </button>
        </div>
      )}

      {view === 'read' && (
        <div className="relative z-10 space-y-4">
          <div className="flex justify-between items-center pb-3 border-b border-white/5">
            <h3 className="font-serif text-2xl font-light text-white">{article.title}</h3>
            <button onClick={() => setView('card')} className="text-slate-400 hover:text-white text-xs">✕ Close</button>
          </div>

          <div className="text-slate-300 text-xs leading-relaxed max-h-[300px] overflow-y-auto pr-2 space-y-3 font-light whitespace-pre-wrap">
            {article.content}
          </div>

          <div className="pt-3 border-t border-white/5 flex justify-end">
            <button 
              onClick={handleMarkRead}
              className="bg-[#c5a572] hover:bg-[#d9b884] text-[#1a1008] text-xs font-semibold px-6 py-2.5 rounded-xl transition"
            >
              {quizzes.length > 0 ? "Take Micro Quiz" : "Complete Reflection"}
            </button>
          </div>
        </div>
      )}

      {view === 'quiz' && (
        <div className="relative z-10 space-y-5">
          <div className="flex justify-between items-center pb-3 border-b border-white/5">
            <h3 className="font-serif text-2xl font-light text-white">Daily Check Quiz</h3>
            <button onClick={() => setView('card')} className="text-slate-400 hover:text-white text-xs">✕ Close</button>
          </div>

          <div className="space-y-6">
            {quizzes.map((quiz, qIdx) => (
              <div key={quiz.id} className="space-y-2.5">
                <span className="text-[10px] text-slate-500 uppercase tracking-widest block">QUESTION {qIdx + 1}</span>
                <p className="text-white text-sm font-medium leading-relaxed">{quiz.question}</p>
                
                <div className="grid gap-2">
                  {quiz.options.map((opt, oIdx) => (
                    <button
                      key={oIdx}
                      onClick={() => setAnswers(prev => ({ ...prev, [quiz.id]: oIdx }))}
                      className={`text-left text-xs p-3.5 rounded-xl border transition ${
                        answers[quiz.id] === oIdx 
                          ? 'bg-[#c5a572]/15 border-[#c5a572] text-[#c5a572]' 
                          : 'bg-white/3 border-white/5 text-slate-300 hover:border-white/20'
                      }`}
                    >
                      {opt}
                    </button>
                  ))}
                </div>
              </div>
            ))}
          </div>

          <div className="pt-4 border-t border-white/5 flex justify-end">
            <button 
              onClick={handleSubmitQuiz}
              disabled={Object.keys(answers).length < quizzes.length}
              className="bg-[#c5a572] hover:bg-[#d9b884] disabled:opacity-50 text-[#1a1008] text-xs font-semibold px-6 py-2.5 rounded-xl transition"
            >
              Submit Reflection Quiz
            </button>
          </div>
        </div>
      )}

      {view === 'completed' && (
        <div className="relative z-10 text-center py-4">
          <div className="text-4xl mb-3">🎉</div>
          <h3 className="font-serif text-2xl font-light text-white mb-2">Reflections Complete!</h3>
          
          {quizResult ? (
            <div className="space-y-4">
              <p className="text-slate-400 text-xs max-w-sm mx-auto leading-relaxed">
                You successfully solved the daily micro-learning session! You scored <strong className="text-[#c5a572]">{quizResult.score}%</strong> and earned <strong className="text-[#c5a572]">{quizResult.points} points</strong>.
              </p>
              
              <div className="space-y-2 text-left bg-white/3 border border-white/5 p-4 rounded-2xl max-h-36 overflow-y-auto">
                <span className="text-[9px] text-slate-500 uppercase tracking-widest font-semibold block">EXPLANATIONS:</span>
                {quizResult.explanations?.map((exp: any, idx: number) => (
                  <p key={idx} className="text-[11px] text-slate-300 leading-relaxed">
                    💡 {exp.explanation}
                  </p>
                ))}
              </div>
            </div>
          ) : (
            <p className="text-slate-400 text-xs max-w-sm mx-auto leading-relaxed">
              Your daily wellness learning check is locked and loaded. Your streak has been recorded!
            </p>
          )}

          <div className="flex justify-center gap-3 mt-6">
            <button 
              onClick={() => setView('read')}
              className="border border-white/10 hover:border-white/20 bg-white/3 hover:bg-white/5 text-slate-300 hover:text-white text-xs font-semibold px-5 py-2.5 rounded-xl transition"
            >
              Read Article again
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
