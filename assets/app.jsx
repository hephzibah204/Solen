// Import required libraries
import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom';

// Define theme colors
const THEMES = {
  emotional: { bg:"#07070f", surface:"#0d0d18", accent:"#a78bfa", soft:"#2d1454", mid:"#1a0a2e", glow:"rgba(167,139,250,0.15)" },
  anxiety:   { bg:"#060f0c", surface:"#0a1a14", accent:"#34d399", soft:"#0a2e1e", mid:"#071a10", glow:"rgba(52,211,153,0.15)" },
  growth:    { bg:"#0a0800", surface:"#141000", accent:"#f59e0b", soft:"#2e1f00", mid:"#1a1200", glow:"rgba(245,158,11,