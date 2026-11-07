// Shared Lucide-style icons for the آرتا فارما automation kit.
// SUBSTITUTION: Lucide icon paths (MIT) stand in for the product's own glyphs.
// Guarded so double-evaluation (stream + mount) is a no-op instead of re-declaring consts.
if (!window.AfIcons) { (function () {
const _icn = (paths, sw = 2) => ({ size = 20, style } = {}) =>
  React.createElement('svg', {
    width: size, height: size, viewBox: '0 0 24 24', fill: 'none',
    stroke: 'currentColor', strokeWidth: sw, strokeLinecap: 'round',
    strokeLinejoin: 'round', style,
  }, paths.map((d, i) => React.createElement('path', { key: i, d })));

const Icons = {
  home: _icn(['M3 10.5 12 3l9 7.5', 'M5 9.8V21h14V9.8']),
  inbox: _icn(['M22 12h-6l-2 3h-4l-2-3H2', 'M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z']),
  send: _icn(['m22 2-7 20-4-9-9-4Z', 'M22 2 11 13']),
  file: _icn(['M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z', 'M14 3v5h5']),
  archive: _icn(['M21 8v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8', 'M2 4h20v4H2z', 'M10 12h4']),
  users: _icn(['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75']),
  cog: _icn(['M12.2 2h-.4a2 2 0 0 0-2 2 2 2 0 0 1-1 1.7 2 2 0 0 1-2 0 2 2 0 0 0-2.7.7l-.2.4a2 2 0 0 0 .7 2.7 2 2 0 0 1 0 2 2 2 0 0 1-1.7 1 2 2 0 0 0-2 2v.4a2 2 0 0 0 2 2 2 2 0 0 1 1.7 1 2 2 0 0 1 0 2 2 2 0 0 0 .7 2.7l.4.2a2 2 0 0 0 2.7-.7 2 2 0 0 1 2 0 2 2 0 0 1 1 1.7 2 2 0 0 0 2 2h.4a2 2 0 0 0 2-2 2 2 0 0 1 1-1.7 2 2 0 0 1 2 0 2 2 0 0 0 2.7-.7l.2-.4a2 2 0 0 0-.7-2.7 2 2 0 0 1 0-2 2 2 0 0 1 1.7-1 2 2 0 0 0 2-2v-.4a2 2 0 0 0-2-2 2 2 0 0 1-1.7-1 2 2 0 0 1 0-2 2 2 0 0 0 .7-2.7l-.2-.4a2 2 0 0 0-2.7-.7 2 2 0 0 1-2 0 2 2 0 0 1-1-1.7 2 2 0 0 0-2-2z', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z']),
  search: _icn(['m21 21-4.3-4.3', 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14z']),
  bell: _icn(['M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9', 'M10.3 21a1.9 1.9 0 0 0 3.4 0']),
  plus: _icn(['M12 5v14', 'M5 12h14']),
  clock: _icn(['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M12 7v5l3 2']),
  check: _icn(['M20 6 9 17l-5-5'], 2.4),
  chart: _icn(['M3 3v18h18', 'M7 15l3-4 3 3 4-6']),
  user: _icn(['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M4 21c0-4 4-6 8-6s8 2 8 6']),
  lock: _icn(['M5 11h14a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2z', 'M8 11V7a4 4 0 0 1 8 0v4']),
  chevronLeft: _icn(['m15 18-6-6 6-6']),
  paperclip: _icn(['m21.4 11.1-9.2 9.2a5 5 0 0 1-7-7l9.1-9.2a3.3 3.3 0 0 1 4.7 4.7l-9.2 9.1a1.7 1.7 0 0 1-2.3-2.3l8.5-8.5']),
  logout: _icn(['M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4', 'm16 17 5-5-5-5', 'M21 12H9']),
  globe: _icn(['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M3.6 9h16.8', 'M3.6 15h16.8', 'M12 3a13 13 0 0 1 0 18 13 13 0 0 1 0-18z']),
  building: _icn(['M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z', 'M10 6h4', 'M10 10h4', 'M10 14h4', 'M2 22h20']),
  pill: _icn(['m10.5 20.5 10-10a5 5 0 0 0-7-7l-10 10a5 5 0 0 0 7 7Z', 'm8.5 8.5 7 7']),
  flask: _icn(['M9 3h6', 'M10 3v6.6a2 2 0 0 1-.3 1L5 20a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-4.7-9.4a2 2 0 0 1-.3-1V3', 'M6.5 15h11']),
};
window.AfIcons = Icons;
})(); }
