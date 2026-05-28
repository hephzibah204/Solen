import React, { useState } from 'react';
import { AuthContext, useAuthProvider } from './hooks/useAuth';
import Layout from './components/Layout';
import Landing from './pages/Landing';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import Chat from './pages/Chat';
import Rituals from './pages/Rituals';
import Timeline from './pages/Timeline';
import Insights from './pages/Insights';
import Family from './pages/Family';
import Profile from './pages/Profile';


function AppInner() {
  const auth = useAuthProvider();
  const [view, setView] = useState('dashboard');
  const [authView, setAuthView] = useState<'login' | 'register' | null>(null);

  // Loading splash
  if (auth.loading) {
    return (
      <div className="min-h-screen bg-slate-950 flex items-center justify-center">
        <div className="text-center">
          <h1 className="text-3xl font-bold bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent mb-3">Solen</h1>
          <div className="flex justify-center gap-1.5">
            {[0,1,2].map(i => (
              <div key={i} className="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style={{ animationDelay: `${i*150}ms` }} />
            ))}
          </div>
        </div>
      </div>
    );
  }

  // Not logged in
  if (!auth.user) {
    if (authView === 'login') {
      return (
        <AuthContext.Provider value={auth}>
          <Login onSwitch={() => setAuthView('register')} />
        </AuthContext.Provider>
      );
    }
    if (authView === 'register') {
      return (
        <AuthContext.Provider value={auth}>
          <Register onSwitch={() => setAuthView('login')} />
        </AuthContext.Provider>
      );
    }
    return (
      <Landing
        onLogin={() => setAuthView('login')}
        onRegister={() => setAuthView('register')}
      />
    );
  }

  // Logged in — route to correct page
  const renderPage = () => {
    switch (view) {
      case 'chat':      return <Chat />;
      case 'rituals':   return <Rituals />;
      case 'timeline':  return <Timeline />;
      case 'insights':  return <Insights />;
      case 'family':    return <Family />;
      case 'profile':   return <Profile onLogout={auth.logout} />;
      default:          return <Dashboard setView={setView} />;
    }
  };

  return (
    <AuthContext.Provider value={auth}>
      <Layout view={view} setView={setView} user={auth.user} onLogout={auth.logout}>
        {renderPage()}
      </Layout>
    </AuthContext.Provider>
  );
}

export default function App() {
  return <AppInner />;
}
