/* @ds-bundle: {"format":4,"namespace":"DesignSystem_e25c50","components":[{"name":"Table","sourcePath":"components/data/Table.jsx"},{"name":"Avatar","sourcePath":"components/display/Avatar.jsx"},{"name":"Badge","sourcePath":"components/display/Badge.jsx"},{"name":"Card","sourcePath":"components/display/Card.jsx"},{"name":"StatCard","sourcePath":"components/display/StatCard.jsx"},{"name":"Tag","sourcePath":"components/display/Tag.jsx"},{"name":"Alert","sourcePath":"components/feedback/Alert.jsx"},{"name":"ProgressBar","sourcePath":"components/feedback/ProgressBar.jsx"},{"name":"WorkflowStepper","sourcePath":"components/feedback/WorkflowStepper.jsx"},{"name":"Button","sourcePath":"components/forms/Button.jsx"},{"name":"Checkbox","sourcePath":"components/forms/Checkbox.jsx"},{"name":"IconButton","sourcePath":"components/forms/IconButton.jsx"},{"name":"Input","sourcePath":"components/forms/Input.jsx"},{"name":"Select","sourcePath":"components/forms/Select.jsx"},{"name":"Switch","sourcePath":"components/forms/Switch.jsx"},{"name":"Textarea","sourcePath":"components/forms/Textarea.jsx"},{"name":"SidebarItem","sourcePath":"components/navigation/SidebarItem.jsx"},{"name":"SidebarNav","sourcePath":"components/navigation/SidebarNav.jsx"},{"name":"Tabs","sourcePath":"components/navigation/Tabs.jsx"}],"sourceHashes":{"components/data/Table.jsx":"b3d52ccb3401","components/display/Avatar.jsx":"9dfba4b0b47b","components/display/Badge.jsx":"2c57683e8303","components/display/Card.jsx":"8f370a29b681","components/display/StatCard.jsx":"bcac3ff5bdc0","components/display/Tag.jsx":"a85cf5302e7b","components/feedback/Alert.jsx":"ced0debd36b7","components/feedback/ProgressBar.jsx":"db94a756d663","components/feedback/WorkflowStepper.jsx":"e12895989e7c","components/forms/Button.jsx":"e93f60e3423b","components/forms/Checkbox.jsx":"a4b2cf8b237a","components/forms/IconButton.jsx":"4667b5a96c1c","components/forms/Input.jsx":"096a7818efc3","components/forms/Select.jsx":"0e24da23970a","components/forms/Switch.jsx":"e967cdb4fc70","components/forms/Textarea.jsx":"6048769f9bf1","components/navigation/SidebarItem.jsx":"83ae93f8ecec","components/navigation/SidebarNav.jsx":"3fa1f4d3dc17","components/navigation/Tabs.jsx":"db6ff54f2e4c","ui_kits/automation/CartableScreen.jsx":"57deebebab6c","ui_kits/automation/ComposeScreen.jsx":"0a4aa1063617","ui_kits/automation/DashboardScreen.jsx":"4eb1a0cc695c","ui_kits/automation/LoginScreen.jsx":"e351be665f81","ui_kits/automation/Shell.jsx":"c5edec4120f6","ui_kits/automation/icons.jsx":"938304ec6dfe"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.DesignSystem_e25c50 = window.DesignSystem_e25c50 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/data/Table.jsx
try { (() => {
/**
 * Table — lightweight RTL data table. Columns describe alignment & render;
 * rows are plain objects. Zebra + hover built in.
 */
function Table({
  columns = [],
  rows = [],
  onRowClick = null,
  empty = 'موردی یافت نشد',
  style = {}
}) {
  const [hover, setHover] = React.useState(-1);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      width: '100%',
      overflowX: 'auto',
      ...style
    }
  }, /*#__PURE__*/React.createElement("table", {
    style: {
      width: '100%',
      borderCollapse: 'collapse',
      fontFamily: 'var(--font-fa)'
    }
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, columns.map(c => /*#__PURE__*/React.createElement("th", {
    key: c.key,
    style: {
      textAlign: c.align || 'start',
      padding: '11px 16px',
      fontSize: 'var(--fs-xs)',
      fontWeight: 600,
      color: 'var(--text-subtle)',
      background: 'var(--surface-sunken)',
      whiteSpace: 'nowrap',
      borderBottom: '1px solid var(--border-subtle)',
      width: c.width || 'auto'
    }
  }, c.header)))), /*#__PURE__*/React.createElement("tbody", null, rows.length === 0 && /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", {
    colSpan: columns.length,
    style: {
      padding: '28px 16px',
      textAlign: 'center',
      color: 'var(--text-subtle)'
    }
  }, empty)), rows.map((row, ri) => /*#__PURE__*/React.createElement("tr", {
    key: ri,
    onClick: onRowClick ? () => onRowClick(row, ri) : undefined,
    onMouseEnter: () => setHover(ri),
    onMouseLeave: () => setHover(-1),
    style: {
      background: hover === ri ? 'var(--blue-50)' : 'var(--surface-card)',
      cursor: onRowClick ? 'pointer' : 'default',
      transition: 'background var(--dur-fast) var(--ease-standard)'
    }
  }, columns.map(c => /*#__PURE__*/React.createElement("td", {
    key: c.key,
    style: {
      padding: '13px 16px',
      fontSize: 'var(--fs-body)',
      color: 'var(--text-body)',
      textAlign: c.align || 'start',
      borderBottom: '1px solid var(--border-subtle)',
      whiteSpace: c.nowrap ? 'nowrap' : 'normal'
    }
  }, c.render ? c.render(row[c.key], row, ri) : row[c.key])))))));
}
Object.assign(__ds_scope, { Table });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Table.jsx", error: String((e && e.message) || e) }); }

// components/display/Avatar.jsx
try { (() => {
/**
 * Avatar — user identity chip. Shows an image, or initials on a brand-tinted fill.
 */
function Avatar({
  name = '',
  src = null,
  size = 'md',
  ring = false,
  style = {}
}) {
  const dims = {
    xs: 24,
    sm: 32,
    md: 40,
    lg: 52,
    xl: 64
  };
  const d = dims[size] || dims.md;
  const initials = name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('');

  // Deterministic tint from name
  const palettes = [['var(--blue-100)', 'var(--blue-700)'], ['var(--teal-100)', 'var(--teal-700)'], ['var(--gold-100)', 'var(--gold-700)'], ['var(--ink-200)', 'var(--ink-700)']];
  let h = 0;
  for (const c of name) h = (h + c.charCodeAt(0)) % palettes.length;
  const [bg, fg] = palettes[h];
  return /*#__PURE__*/React.createElement("span", {
    style: {
      width: d,
      height: d,
      flexShrink: 0,
      borderRadius: '50%',
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: src ? 'var(--ink-100)' : bg,
      color: fg,
      overflow: 'hidden',
      fontFamily: 'var(--font-fa)',
      fontWeight: 'var(--fw-semibold)',
      fontSize: d * 0.38,
      boxShadow: ring ? '0 0 0 2px var(--white), 0 0 0 4px var(--brand)' : 'none',
      ...style
    }
  }, src ? /*#__PURE__*/React.createElement("img", {
    src: src,
    alt: name,
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'cover'
    }
  }) : initials);
}
Object.assign(__ds_scope, { Avatar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/display/Avatar.jsx", error: String((e && e.message) || e) }); }

// components/display/Badge.jsx
try { (() => {
/**
 * Badge — small status pill. Used for workflow states & counts.
 */
function Badge({
  children,
  tone = 'neutral',
  variant = 'soft',
  dot = false,
  style = {}
}) {
  const tones = {
    neutral: {
      soft: ['var(--ink-100)', 'var(--ink-700)'],
      solid: ['var(--ink-600)', '#fff']
    },
    brand: {
      soft: ['var(--blue-50)', 'var(--blue-700)'],
      solid: ['var(--brand)', '#fff']
    },
    gold: {
      soft: ['var(--gold-50)', 'var(--gold-700)'],
      solid: ['var(--gold-500)', 'var(--ink-900)']
    },
    teal: {
      soft: ['var(--teal-50)', 'var(--teal-700)'],
      solid: ['var(--teal-600)', '#fff']
    },
    success: {
      soft: ['var(--success-50)', 'var(--success-600)'],
      solid: ['var(--success-500)', '#fff']
    },
    warning: {
      soft: ['var(--warning-50)', 'var(--warning-600)'],
      solid: ['var(--warning-500)', 'var(--ink-900)']
    },
    danger: {
      soft: ['var(--danger-50)', 'var(--danger-600)'],
      solid: ['var(--danger-500)', '#fff']
    }
  };
  const [bg, fg] = (tones[tone] || tones.neutral)[variant] || tones.neutral.soft;
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 6,
      background: bg,
      color: fg,
      fontFamily: 'var(--font-fa)',
      fontSize: 'var(--fs-xs)',
      fontWeight: 'var(--fw-semibold)',
      padding: '4px 11px',
      borderRadius: 'var(--r-pill)',
      lineHeight: 1.4,
      whiteSpace: 'nowrap',
      ...style
    }
  }, dot && /*#__PURE__*/React.createElement("span", {
    style: {
      width: 6,
      height: 6,
      borderRadius: '50%',
      background: fg,
      opacity: 0.9
    }
  }), children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/display/Badge.jsx", error: String((e && e.message) || e) }); }

// components/display/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Card — the base surface container for the automation UI.
 */
function Card({
  children,
  padding = 'md',
  interactive = false,
  accent = null,
  style = {},
  onClick,
  ...rest
}) {
  const pads = {
    none: 0,
    sm: 'var(--sp-4)',
    md: 'var(--sp-6)',
    lg: 'var(--sp-7)'
  };
  const [hover, setHover] = React.useState(false);
  const accentBar = accent && {
    borderTop: `3px solid ${accent === 'gold' ? 'var(--gold-500)' : accent === 'teal' ? 'var(--teal-500)' : accent === 'danger' ? 'var(--danger-500)' : 'var(--brand)'}`
  };
  return /*#__PURE__*/React.createElement("div", _extends({
    onClick: onClick,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      background: 'var(--surface-card)',
      border: '1px solid var(--border-subtle)',
      borderRadius: 'var(--r-card)',
      padding: pads[padding] ?? pads.md,
      boxShadow: interactive && hover ? 'var(--shadow-lg)' : 'var(--shadow-sm)',
      transform: interactive && hover ? 'translateY(-2px)' : 'none',
      transition: 'var(--t-surface)',
      cursor: interactive || onClick ? 'pointer' : 'default',
      ...accentBar,
      ...style
    }
  }, rest), children);
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/display/Card.jsx", error: String((e && e.message) || e) }); }

// components/display/StatCard.jsx
try { (() => {
/**
 * StatCard — KPI tile for dashboards. Big number + label + optional delta & icon.
 */
function StatCard({
  label,
  value,
  delta = null,
  trend = 'up',
  icon = null,
  accent = 'brand',
  style = {}
}) {
  const accents = {
    brand: 'var(--grad-brand-soft)',
    teal: 'var(--grad-teal)',
    gold: 'var(--grad-gold)',
    ink: 'linear-gradient(135deg,var(--ink-700),var(--ink-900))'
  };
  const goldText = accent === 'gold';
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--surface-card)',
      border: '1px solid var(--border-subtle)',
      borderRadius: 'var(--r-card)',
      padding: 'var(--sp-5)',
      boxShadow: 'var(--shadow-sm)',
      display: 'flex',
      flexDirection: 'column',
      gap: 14,
      minWidth: 0,
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-sm)',
      color: 'var(--text-muted)',
      fontWeight: 'var(--fw-medium)'
    }
  }, label), icon && /*#__PURE__*/React.createElement("span", {
    style: {
      width: 38,
      height: 38,
      borderRadius: 'var(--r-md)',
      background: accents[accent] || accents.brand,
      color: goldText ? 'var(--ink-900)' : '#fff',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      flexShrink: 0
    }
  }, icon)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'baseline',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 30,
      fontWeight: 'var(--fw-black)',
      color: 'var(--text-strong)',
      lineHeight: 1
    }
  }, value), delta && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-sm)',
      fontWeight: 'var(--fw-semibold)',
      color: trend === 'up' ? 'var(--success-600)' : 'var(--danger-600)',
      display: 'inline-flex',
      alignItems: 'center',
      gap: 2
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "13",
    height: "13",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2.4",
    strokeLinecap: "round",
    strokeLinejoin: "round",
    style: {
      transform: trend === 'up' ? 'none' : 'rotate(180deg)'
    }
  }, /*#__PURE__*/React.createElement("path", {
    d: "M12 19V5M5 12l7-7 7 7"
  })), delta)));
}
Object.assign(__ds_scope, { StatCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/display/StatCard.jsx", error: String((e && e.message) || e) }); }

// components/display/Tag.jsx
try { (() => {
/**
 * Tag — removable label/chip for filters, categories, recipients.
 */
function Tag({
  children,
  color = 'neutral',
  onRemove,
  style = {}
}) {
  const colors = {
    neutral: ['var(--ink-100)', 'var(--ink-700)', 'var(--border-default)'],
    brand: ['var(--blue-50)', 'var(--blue-700)', 'var(--blue-200)'],
    teal: ['var(--teal-50)', 'var(--teal-700)', 'var(--teal-300)'],
    gold: ['var(--gold-50)', 'var(--gold-700)', 'var(--gold-300)']
  };
  const [bg, fg, bd] = colors[color] || colors.neutral;
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 7,
      background: bg,
      color: fg,
      border: `1px solid ${bd}`,
      fontFamily: 'var(--font-fa)',
      fontSize: 'var(--fs-sm)',
      fontWeight: 'var(--fw-medium)',
      padding: '5px 12px',
      borderRadius: 'var(--r-sm)',
      ...style
    }
  }, children, onRemove && /*#__PURE__*/React.createElement("button", {
    onClick: onRemove,
    "aria-label": "\u062D\u0630\u0641",
    style: {
      border: 'none',
      background: 'transparent',
      cursor: 'pointer',
      color: fg,
      display: 'flex',
      padding: 0,
      opacity: 0.7
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "13",
    height: "13",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2.4",
    strokeLinecap: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M18 6 6 18M6 6l12 12"
  }))));
}
Object.assign(__ds_scope, { Tag });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/display/Tag.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Alert.jsx
try { (() => {
/**
 * Alert — inline contextual message banner.
 */
function Alert({
  children,
  title,
  tone = 'info',
  onClose,
  style = {}
}) {
  const tones = {
    info: ['var(--info-50)', 'var(--info-600)', 'var(--info-500)'],
    success: ['var(--success-50)', 'var(--success-600)', 'var(--success-500)'],
    warning: ['var(--warning-50)', 'var(--warning-600)', 'var(--warning-500)'],
    danger: ['var(--danger-50)', 'var(--danger-600)', 'var(--danger-500)']
  };
  const [bg, fg, accent] = tones[tone] || tones.info;
  const icons = {
    info: 'M12 16v-4M12 8h.01M12 2a10 10 0 100 20 10 10 0 000-20',
    success: 'M20 6 9 17l-5-5',
    warning: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L14.4 3.9a2 2 0 00-3.4 0z',
    danger: 'M12 8v4M12 16h.01M12 2a10 10 0 100 20 10 10 0 000-20'
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 12,
      alignItems: 'flex-start',
      background: bg,
      border: `1px solid ${accent}33`,
      borderInlineStart: `3px solid ${accent}`,
      borderRadius: 'var(--r-md)',
      padding: '13px 16px',
      ...style
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      color: accent,
      flexShrink: 0,
      marginTop: 1,
      display: 'flex'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "19",
    height: "19",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: icons[tone] || icons.info
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, title && /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 'var(--fw-semibold)',
      color: fg,
      fontSize: 'var(--fs-body)',
      marginBottom: children ? 3 : 0
    }
  }, title), children && /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--fs-sm)',
      color: 'var(--text-body)',
      lineHeight: 'var(--lh-snug)'
    }
  }, children)), onClose && /*#__PURE__*/React.createElement("button", {
    onClick: onClose,
    "aria-label": "\u0628\u0633\u062A\u0646",
    style: {
      border: 'none',
      background: 'transparent',
      cursor: 'pointer',
      color: 'var(--text-subtle)',
      display: 'flex',
      padding: 0
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2.2",
    strokeLinecap: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M18 6 6 18M6 6l12 12"
  }))));
}
Object.assign(__ds_scope, { Alert });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Alert.jsx", error: String((e && e.message) || e) }); }

// components/feedback/ProgressBar.jsx
try { (() => {
/**
 * ProgressBar — linear progress / workflow completion indicator.
 */
function ProgressBar({
  value = 0,
  max = 100,
  tone = 'brand',
  showLabel = false,
  size = 'md',
  style = {}
}) {
  const pct = Math.max(0, Math.min(100, value / max * 100));
  const fills = {
    brand: 'var(--grad-brand-soft)',
    teal: 'var(--grad-teal)',
    gold: 'var(--grad-gold)',
    success: 'var(--success-500)',
    danger: 'var(--danger-500)'
  };
  const h = {
    sm: 6,
    md: 9,
    lg: 12
  }[size] || 9;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12,
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      height: h,
      background: 'var(--ink-150)',
      borderRadius: 'var(--r-pill)',
      overflow: 'hidden',
      boxShadow: 'var(--shadow-inset)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: `${pct}%`,
      height: '100%',
      background: fills[tone] || fills.brand,
      borderRadius: 'var(--r-pill)',
      transition: 'width var(--dur-slow) var(--ease-out)'
    }
  })), showLabel && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-sm)',
      fontWeight: 'var(--fw-semibold)',
      color: 'var(--text-muted)',
      minWidth: 38,
      textAlign: 'left'
    }
  }, Math.round(pct), "\u066A"));
}
Object.assign(__ds_scope, { ProgressBar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/ProgressBar.jsx", error: String((e && e.message) || e) }); }

// components/feedback/WorkflowStepper.jsx
try { (() => {
/**
 * WorkflowStepper — horizontal step tracker for approval / gardesh-e kar flows.
 */
function WorkflowStepper({
  steps = [],
  current = 0,
  style = {}
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'flex-start',
      width: '100%',
      ...style
    }
  }, steps.map((s, i) => {
    const label = typeof s === 'string' ? s : s.label;
    const done = i < current;
    const active = i === current;
    const circleBg = done ? 'var(--grad-teal)' : active ? 'var(--grad-brand-soft)' : 'var(--ink-150)';
    const circleFg = done || active ? '#fff' : 'var(--text-subtle)';
    return /*#__PURE__*/React.createElement(React.Fragment, {
      key: i
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: 8,
        flexShrink: 0,
        width: 96
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        width: 34,
        height: 34,
        borderRadius: '50%',
        background: circleBg,
        color: circleFg,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontWeight: 'var(--fw-bold)',
        fontSize: 14,
        boxShadow: active ? 'var(--shadow-brand)' : 'none',
        border: !done && !active ? '1.5px solid var(--border-default)' : 'none'
      }
    }, done ? /*#__PURE__*/React.createElement("svg", {
      width: "16",
      height: "16",
      viewBox: "0 0 24 24",
      fill: "none",
      stroke: "#fff",
      strokeWidth: "3",
      strokeLinecap: "round",
      strokeLinejoin: "round"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M20 6 9 17l-5-5"
    })) : i + 1), /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: 'var(--fs-xs)',
        fontWeight: active ? 'var(--fw-semibold)' : 'var(--fw-regular)',
        color: active ? 'var(--text-strong)' : 'var(--text-muted)',
        textAlign: 'center',
        lineHeight: 1.4
      }
    }, label)), i < steps.length - 1 && /*#__PURE__*/React.createElement("div", {
      style: {
        flex: 1,
        height: 2,
        background: i < current ? 'var(--teal-400)' : 'var(--border-default)',
        marginTop: 16,
        borderRadius: 2,
        minWidth: 20
      }
    }));
  }));
}
Object.assign(__ds_scope, { WorkflowStepper });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/WorkflowStepper.jsx", error: String((e && e.message) || e) }); }

// components/forms/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Button — primary action control for the آرتا فارما automation UI.
 * Inline styles only; all values come from design-system CSS custom properties.
 */
function Button({
  children,
  variant = 'primary',
  size = 'md',
  block = false,
  disabled = false,
  loading = false,
  iconStart = null,
  iconEnd = null,
  type = 'button',
  onClick,
  style = {},
  ...rest
}) {
  const sizes = {
    sm: {
      padding: '7px 14px',
      fontSize: 'var(--fs-sm)',
      height: 34,
      radius: 'var(--r-sm)',
      gap: 6
    },
    md: {
      padding: '10px 20px',
      fontSize: 'var(--fs-body)',
      height: 42,
      radius: 'var(--r-control)',
      gap: 8
    },
    lg: {
      padding: '13px 26px',
      fontSize: 'var(--fs-body-lg)',
      height: 50,
      radius: 'var(--r-lg)',
      gap: 10
    }
  };
  const s = sizes[size] || sizes.md;
  const variants = {
    primary: {
      background: 'var(--grad-brand-soft)',
      color: 'var(--white)',
      border: '1px solid transparent',
      boxShadow: 'var(--shadow-brand)'
    },
    secondary: {
      background: 'var(--white)',
      color: 'var(--brand)',
      border: '1px solid var(--border-brand)',
      boxShadow: 'var(--shadow-xs)'
    },
    gold: {
      background: 'var(--grad-gold)',
      color: 'var(--ink-900)',
      border: '1px solid transparent',
      boxShadow: 'var(--shadow-gold)'
    },
    teal: {
      background: 'var(--grad-teal)',
      color: 'var(--white)',
      border: '1px solid transparent',
      boxShadow: 'var(--shadow-teal)'
    },
    ghost: {
      background: 'transparent',
      color: 'var(--text-body)',
      border: '1px solid transparent',
      boxShadow: 'none'
    },
    danger: {
      background: 'var(--danger-500)',
      color: 'var(--white)',
      border: '1px solid transparent',
      boxShadow: '0 6px 16px rgba(227,77,95,0.28)'
    }
  };
  const v = variants[variant] || variants.primary;
  return /*#__PURE__*/React.createElement("button", _extends({
    type: type,
    disabled: disabled || loading,
    onClick: onClick,
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      gap: s.gap,
      width: block ? '100%' : 'auto',
      height: s.height,
      padding: s.padding,
      fontSize: s.fontSize,
      fontFamily: 'var(--font-fa)',
      fontWeight: 'var(--fw-semibold)',
      lineHeight: 1,
      letterSpacing: 'var(--ls-tight)',
      borderRadius: s.radius,
      cursor: disabled || loading ? 'not-allowed' : 'pointer',
      opacity: disabled ? 0.5 : 1,
      transition: 'var(--t-control)',
      whiteSpace: 'nowrap',
      userSelect: 'none',
      ...v,
      ...style
    },
    onMouseDown: e => {
      if (!disabled && !loading) e.currentTarget.style.transform = 'translateY(1px) scale(0.99)';
    },
    onMouseUp: e => {
      e.currentTarget.style.transform = 'none';
    },
    onMouseLeave: e => {
      e.currentTarget.style.transform = 'none';
    }
  }, rest), loading && /*#__PURE__*/React.createElement("span", {
    style: {
      width: 15,
      height: 15,
      borderRadius: '50%',
      border: '2px solid currentColor',
      borderTopColor: 'transparent',
      display: 'inline-block',
      animation: 'af-spin 0.7s linear infinite'
    }
  }), !loading && iconStart, children, !loading && iconEnd, /*#__PURE__*/React.createElement("style", null, '@keyframes af-spin{to{transform:rotate(360deg)}}'));
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Button.jsx", error: String((e && e.message) || e) }); }

// components/forms/Checkbox.jsx
try { (() => {
/**
 * Checkbox — labelled control with a brand-gradient checked state.
 */
function Checkbox({
  label,
  checked,
  defaultChecked,
  disabled = false,
  onChange,
  id,
  style = {}
}) {
  const [internal, setInternal] = React.useState(defaultChecked || false);
  const isControlled = checked !== undefined;
  const on = isControlled ? checked : internal;
  const toggle = e => {
    if (disabled) return;
    if (!isControlled) setInternal(!on);
    onChange && onChange(!on, e);
  };
  return /*#__PURE__*/React.createElement("label", {
    htmlFor: id,
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 10,
      cursor: disabled ? 'not-allowed' : 'pointer',
      opacity: disabled ? 0.5 : 1,
      userSelect: 'none',
      ...style
    }
  }, /*#__PURE__*/React.createElement("span", {
    onClick: toggle,
    role: "checkbox",
    "aria-checked": on,
    style: {
      width: 20,
      height: 20,
      flexShrink: 0,
      borderRadius: 'var(--r-xs)',
      border: on ? '1.5px solid transparent' : '1.5px solid var(--border-strong)',
      background: on ? 'var(--grad-brand-soft)' : 'var(--white)',
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      transition: 'var(--t-control)',
      boxShadow: on ? 'var(--shadow-xs)' : 'var(--shadow-inset)'
    }
  }, on && /*#__PURE__*/React.createElement("svg", {
    width: "13",
    height: "13",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "#fff",
    strokeWidth: "3.2",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M20 6 9 17l-5-5"
  }))), label && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-body)',
      color: 'var(--text-body)'
    }
  }, label), /*#__PURE__*/React.createElement("input", {
    id: id,
    type: "checkbox",
    checked: on,
    onChange: () => {},
    style: {
      display: 'none'
    }
  }));
}
Object.assign(__ds_scope, { Checkbox });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Checkbox.jsx", error: String((e && e.message) || e) }); }

// components/forms/IconButton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * IconButton — square control wrapping a single icon glyph.
 * Pass a Lucide/SVG node as children.
 */
function IconButton({
  children,
  variant = 'ghost',
  size = 'md',
  disabled = false,
  label,
  onClick,
  style = {},
  ...rest
}) {
  const dims = {
    sm: 32,
    md: 40,
    lg: 46
  };
  const d = dims[size] || dims.md;
  const variants = {
    ghost: {
      background: 'transparent',
      color: 'var(--text-muted)',
      border: '1px solid transparent'
    },
    soft: {
      background: 'var(--brand-soft)',
      color: 'var(--brand)',
      border: '1px solid transparent'
    },
    outline: {
      background: 'var(--white)',
      color: 'var(--text-body)',
      border: '1px solid var(--border-default)'
    },
    brand: {
      background: 'var(--grad-brand-soft)',
      color: 'var(--white)',
      border: '1px solid transparent',
      boxShadow: 'var(--shadow-brand)'
    }
  };
  const v = variants[variant] || variants.ghost;
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    "aria-label": label,
    title: label,
    disabled: disabled,
    onClick: onClick,
    style: {
      width: d,
      height: d,
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      borderRadius: 'var(--r-control)',
      cursor: disabled ? 'not-allowed' : 'pointer',
      opacity: disabled ? 0.45 : 1,
      transition: 'var(--t-control)',
      ...v,
      ...style
    },
    onMouseEnter: e => {
      if (variant === 'ghost' && !disabled) e.currentTarget.style.background = 'var(--ink-100)';
    },
    onMouseLeave: e => {
      if (variant === 'ghost') e.currentTarget.style.background = 'transparent';
    }
  }, rest), children);
}
Object.assign(__ds_scope, { IconButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/IconButton.jsx", error: String((e && e.message) || e) }); }

// components/forms/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Input — text field with RTL label, optional leading icon, hint & error.
 */
function Input({
  label,
  value,
  defaultValue,
  placeholder,
  type = 'text',
  icon = null,
  hint,
  error,
  disabled = false,
  required = false,
  onChange,
  id,
  style = {},
  ...rest
}) {
  const [focused, setFocused] = React.useState(false);
  const invalid = Boolean(error);
  const borderColor = invalid ? 'var(--danger-500)' : focused ? 'var(--brand)' : 'var(--border-default)';
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 6,
      width: '100%',
      ...style
    }
  }, label && /*#__PURE__*/React.createElement("label", {
    htmlFor: id,
    style: {
      font: 'var(--text-label)',
      color: 'var(--text-body)'
    }
  }, label, required && /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--danger-500)',
      marginInlineStart: 3
    }
  }, "*")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8,
      background: disabled ? 'var(--ink-100)' : 'var(--white)',
      border: `1.5px solid ${borderColor}`,
      borderRadius: 'var(--r-control)',
      padding: '0 14px',
      height: 44,
      transition: 'var(--t-control)',
      boxShadow: focused ? `0 0 0 3px var(--focus-ring)` : 'var(--shadow-inset)'
    }
  }, icon && /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--text-subtle)',
      display: 'flex',
      flexShrink: 0
    }
  }, icon), /*#__PURE__*/React.createElement("input", _extends({
    id: id,
    type: type,
    value: value,
    defaultValue: defaultValue,
    placeholder: placeholder,
    disabled: disabled,
    onChange: onChange,
    onFocus: () => setFocused(true),
    onBlur: () => setFocused(false),
    style: {
      flex: 1,
      border: 'none',
      outline: 'none',
      background: 'transparent',
      font: 'inherit',
      fontFamily: 'var(--font-fa)',
      fontSize: 'var(--fs-body)',
      color: 'var(--text-strong)',
      minWidth: 0
    }
  }, rest))), (hint || error) && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-xs)',
      color: invalid ? 'var(--danger-600)' : 'var(--text-subtle)'
    }
  }, error || hint));
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Input.jsx", error: String((e && e.message) || e) }); }

// components/forms/Select.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Select — styled native dropdown with RTL label. Chevron on the left (RTL).
 */
function Select({
  label,
  value,
  defaultValue,
  options = [],
  placeholder = 'انتخاب کنید',
  disabled = false,
  hint,
  error,
  onChange,
  id,
  style = {},
  ...rest
}) {
  const [focused, setFocused] = React.useState(false);
  const invalid = Boolean(error);
  const borderColor = invalid ? 'var(--danger-500)' : focused ? 'var(--brand)' : 'var(--border-default)';
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 6,
      width: '100%',
      ...style
    }
  }, label && /*#__PURE__*/React.createElement("label", {
    htmlFor: id,
    style: {
      font: 'var(--text-label)',
      color: 'var(--text-body)'
    }
  }, label), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      display: 'flex',
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement("select", _extends({
    id: id,
    value: value,
    defaultValue: defaultValue,
    disabled: disabled,
    onChange: onChange,
    onFocus: () => setFocused(true),
    onBlur: () => setFocused(false),
    style: {
      width: '100%',
      height: 44,
      appearance: 'none',
      WebkitAppearance: 'none',
      background: disabled ? 'var(--ink-100)' : 'var(--white)',
      border: `1.5px solid ${borderColor}`,
      borderRadius: 'var(--r-control)',
      padding: '0 14px',
      paddingInlineStart: 38,
      fontFamily: 'var(--font-fa)',
      fontSize: 'var(--fs-body)',
      color: value ? 'var(--text-strong)' : 'var(--text-subtle)',
      cursor: disabled ? 'not-allowed' : 'pointer',
      outline: 'none',
      boxShadow: focused ? '0 0 0 3px var(--focus-ring)' : 'var(--shadow-inset)',
      transition: 'var(--t-control)'
    }
  }, rest), placeholder && /*#__PURE__*/React.createElement("option", {
    value: "",
    disabled: true
  }, placeholder), options.map(o => {
    const val = typeof o === 'string' ? o : o.value;
    const lab = typeof o === 'string' ? o : o.label;
    return /*#__PURE__*/React.createElement("option", {
      key: val,
      value: val
    }, lab);
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      insetInlineStart: 14,
      pointerEvents: 'none',
      color: 'var(--text-subtle)',
      display: 'flex'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2.2",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "m6 9 6 6 6-6"
  })))), (hint || error) && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-xs)',
      color: invalid ? 'var(--danger-600)' : 'var(--text-subtle)'
    }
  }, error || hint));
}
Object.assign(__ds_scope, { Select });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Select.jsx", error: String((e && e.message) || e) }); }

// components/forms/Switch.jsx
try { (() => {
/**
 * Switch — on/off toggle. Teal when active. RTL: thumb rests on the right when off.
 */
function Switch({
  checked,
  defaultChecked,
  disabled = false,
  label,
  onChange,
  size = 'md',
  id,
  style = {}
}) {
  const [internal, setInternal] = React.useState(defaultChecked || false);
  const isControlled = checked !== undefined;
  const on = isControlled ? checked : internal;
  const dims = {
    sm: {
      w: 36,
      h: 20,
      k: 14
    },
    md: {
      w: 46,
      h: 26,
      k: 20
    }
  }[size] || {
    w: 46,
    h: 26,
    k: 20
  };
  const toggle = e => {
    if (disabled) return;
    if (!isControlled) setInternal(!on);
    onChange && onChange(!on, e);
  };
  return /*#__PURE__*/React.createElement("label", {
    htmlFor: id,
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 10,
      cursor: disabled ? 'not-allowed' : 'pointer',
      opacity: disabled ? 0.5 : 1,
      userSelect: 'none',
      ...style
    }
  }, /*#__PURE__*/React.createElement("span", {
    onClick: toggle,
    role: "switch",
    "aria-checked": on,
    style: {
      width: dims.w,
      height: dims.h,
      flexShrink: 0,
      borderRadius: 'var(--r-pill)',
      background: on ? 'var(--grad-teal)' : 'var(--ink-300)',
      position: 'relative',
      transition: 'var(--t-control)',
      boxShadow: 'var(--shadow-inset)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      top: (dims.h - dims.k) / 2,
      insetInlineEnd: on ? dims.w - dims.k - (dims.h - dims.k) / 2 : (dims.h - dims.k) / 2,
      width: dims.k,
      height: dims.k,
      borderRadius: '50%',
      background: 'var(--white)',
      boxShadow: 'var(--shadow-sm)',
      transition: 'var(--t-control)'
    }
  })), label && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-body)',
      color: 'var(--text-body)'
    }
  }, label), /*#__PURE__*/React.createElement("input", {
    id: id,
    type: "checkbox",
    checked: on,
    onChange: () => {},
    style: {
      display: 'none'
    }
  }));
}
Object.assign(__ds_scope, { Switch });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Switch.jsx", error: String((e && e.message) || e) }); }

// components/forms/Textarea.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Textarea — multi-line field for letter bodies & notes. RTL, auto-styled.
 */
function Textarea({
  label,
  value,
  defaultValue,
  placeholder,
  rows = 4,
  disabled = false,
  hint,
  error,
  onChange,
  id,
  style = {},
  ...rest
}) {
  const [focused, setFocused] = React.useState(false);
  const invalid = Boolean(error);
  const borderColor = invalid ? 'var(--danger-500)' : focused ? 'var(--brand)' : 'var(--border-default)';
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 6,
      width: '100%',
      ...style
    }
  }, label && /*#__PURE__*/React.createElement("label", {
    htmlFor: id,
    style: {
      font: 'var(--text-label)',
      color: 'var(--text-body)'
    }
  }, label), /*#__PURE__*/React.createElement("textarea", _extends({
    id: id,
    rows: rows,
    value: value,
    defaultValue: defaultValue,
    placeholder: placeholder,
    disabled: disabled,
    onChange: onChange,
    onFocus: () => setFocused(true),
    onBlur: () => setFocused(false),
    style: {
      width: '100%',
      resize: 'vertical',
      boxSizing: 'border-box',
      background: disabled ? 'var(--ink-100)' : 'var(--white)',
      border: `1.5px solid ${borderColor}`,
      borderRadius: 'var(--r-control)',
      padding: '12px 14px',
      fontFamily: 'var(--font-fa)',
      fontSize: 'var(--fs-body)',
      lineHeight: 'var(--lh-normal)',
      color: 'var(--text-strong)',
      outline: 'none',
      boxShadow: focused ? '0 0 0 3px var(--focus-ring)' : 'var(--shadow-inset)',
      transition: 'var(--t-control)'
    }
  }, rest)), (hint || error) && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-xs)',
      color: invalid ? 'var(--danger-600)' : 'var(--text-subtle)'
    }
  }, error || hint));
}
Object.assign(__ds_scope, { Textarea });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Textarea.jsx", error: String((e && e.message) || e) }); }

// components/navigation/SidebarItem.jsx
try { (() => {
/**
 * SidebarItem — a single navigation row for the app rail. Icon + label,
 * active state (gradient pill), optional trailing count badge.
 */
function SidebarItem({
  icon = null,
  label,
  active = false,
  count = null,
  onClick,
  style = {}
}) {
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("button", {
    onClick: onClick,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12,
      width: '100%',
      padding: '11px 14px',
      borderRadius: 'var(--r-md)',
      border: 'none',
      cursor: 'pointer',
      textAlign: 'start',
      fontFamily: 'var(--font-fa)',
      fontSize: 'var(--fs-body)',
      fontWeight: active ? 600 : 500,
      color: active ? 'var(--white)' : hover ? 'var(--text-strong)' : 'var(--text-muted)',
      background: active ? 'var(--grad-brand-soft)' : hover ? 'var(--ink-100)' : 'transparent',
      boxShadow: active ? 'var(--shadow-brand)' : 'none',
      transition: 'var(--t-control)',
      ...style
    }
  }, icon && /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      flexShrink: 0,
      opacity: active ? 1 : 0.9
    }
  }, icon), /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1
    }
  }, label), count != null && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-xs)',
      fontWeight: 600,
      minWidth: 22,
      textAlign: 'center',
      padding: '2px 7px',
      borderRadius: 'var(--r-pill)',
      background: active ? 'rgba(255,255,255,0.22)' : 'var(--ink-150)',
      color: active ? 'var(--white)' : 'var(--text-muted)'
    }
  }, count));
}
Object.assign(__ds_scope, { SidebarItem });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/SidebarItem.jsx", error: String((e && e.message) || e) }); }

// components/navigation/SidebarNav.jsx
try { (() => {
/**
 * SidebarNav — the primary right-side navigation rail for the automation app.
 * RTL: sits on the right. Renders a brand header, item list, optional counts.
 */
function SidebarNav({
  items = [],
  active,
  onSelect,
  header = null,
  footer = null,
  style = {}
}) {
  return /*#__PURE__*/React.createElement("nav", {
    style: {
      width: 'var(--sidebar-w)',
      flexShrink: 0,
      height: '100%',
      background: 'var(--white)',
      borderInlineStart: '1px solid var(--border-subtle)',
      display: 'flex',
      flexDirection: 'column',
      ...style
    }
  }, header && /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '18px 18px 12px'
    }
  }, header), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      overflowY: 'auto',
      padding: '6px 12px'
    }
  }, items.map(it => {
    if (it.section) {
      return /*#__PURE__*/React.createElement("div", {
        key: it.section,
        style: {
          fontSize: 'var(--fs-xs)',
          fontWeight: 'var(--fw-semibold)',
          color: 'var(--text-subtle)',
          padding: '16px 10px 6px',
          letterSpacing: '.02em'
        }
      }, it.section);
    }
    const on = it.id === active;
    return /*#__PURE__*/React.createElement("button", {
      key: it.id,
      onClick: () => onSelect && onSelect(it.id),
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 11,
        width: '100%',
        padding: '10px 12px',
        marginBottom: 2,
        border: 'none',
        cursor: 'pointer',
        borderRadius: 'var(--r-md)',
        textAlign: 'start',
        fontFamily: 'var(--font-fa)',
        fontSize: 'var(--fs-body)',
        fontWeight: on ? 'var(--fw-semibold)' : 'var(--fw-regular)',
        background: on ? 'var(--brand-soft)' : 'transparent',
        color: on ? 'var(--brand-strong)' : 'var(--text-body)',
        transition: 'var(--t-control)',
        position: 'relative'
      },
      onMouseEnter: e => {
        if (!on) e.currentTarget.style.background = 'var(--ink-50)';
      },
      onMouseLeave: e => {
        if (!on) e.currentTarget.style.background = 'transparent';
      }
    }, on && /*#__PURE__*/React.createElement("span", {
      style: {
        position: 'absolute',
        insetInlineEnd: -12,
        top: 8,
        bottom: 8,
        width: 3,
        background: 'var(--grad-brand-soft)',
        borderRadius: 3
      }
    }), it.icon && /*#__PURE__*/React.createElement("span", {
      style: {
        display: 'flex',
        color: on ? 'var(--brand)' : 'var(--text-subtle)',
        flexShrink: 0
      }
    }, it.icon), /*#__PURE__*/React.createElement("span", {
      style: {
        flex: 1
      }
    }, it.label), it.count != null && /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: 'var(--fs-xs)',
        fontWeight: 'var(--fw-bold)',
        minWidth: 22,
        textAlign: 'center',
        padding: '2px 7px',
        borderRadius: 'var(--r-pill)',
        background: on ? 'var(--brand)' : 'var(--ink-150)',
        color: on ? '#fff' : 'var(--text-muted)'
      }
    }, it.count));
  })), footer && /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 14,
      borderTop: '1px solid var(--border-subtle)'
    }
  }, footer));
}
Object.assign(__ds_scope, { SidebarNav });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/SidebarNav.jsx", error: String((e && e.message) || e) }); }

// components/navigation/Tabs.jsx
try { (() => {
/**
 * Tabs — underline tab bar for switching views within a page.
 */
function Tabs({
  tabs = [],
  active,
  onSelect,
  style = {}
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 4,
      borderBottom: '1.5px solid var(--border-subtle)',
      ...style
    }
  }, tabs.map(t => {
    const id = typeof t === 'string' ? t : t.id;
    const label = typeof t === 'string' ? t : t.label;
    const count = typeof t === 'object' ? t.count : null;
    const on = id === active;
    return /*#__PURE__*/React.createElement("button", {
      key: id,
      onClick: () => onSelect && onSelect(id),
      style: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: 8,
        border: 'none',
        background: 'transparent',
        cursor: 'pointer',
        padding: '11px 16px',
        fontFamily: 'var(--font-fa)',
        fontSize: 'var(--fs-body)',
        fontWeight: on ? 'var(--fw-semibold)' : 'var(--fw-regular)',
        color: on ? 'var(--brand-strong)' : 'var(--text-muted)',
        borderBottom: on ? '2.5px solid var(--brand)' : '2.5px solid transparent',
        marginBottom: -1.5,
        transition: 'var(--t-control)'
      },
      onMouseEnter: e => {
        if (!on) e.currentTarget.style.color = 'var(--text-strong)';
      },
      onMouseLeave: e => {
        if (!on) e.currentTarget.style.color = 'var(--text-muted)';
      }
    }, label, count != null && /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: 'var(--fs-xs)',
        fontWeight: 'var(--fw-bold)',
        padding: '1px 7px',
        borderRadius: 'var(--r-pill)',
        background: on ? 'var(--brand-soft)' : 'var(--ink-150)',
        color: on ? 'var(--brand)' : 'var(--text-subtle)'
      }
    }, count));
  }));
}
Object.assign(__ds_scope, { Tabs });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/Tabs.jsx", error: String((e && e.message) || e) }); }

// ui_kits/automation/CartableScreen.jsx
try { (() => {
// CartableScreen — letters inbox: tabs, filters, master table + detail preview.
function CartableScreen({
  onCompose
}) {
  const {
    Card,
    Tabs,
    Table,
    Badge,
    Avatar,
    Tag,
    Button,
    IconButton
  } = window.DesignSystem_e25c50;
  const I = window.AfIcons;
  const [tab, setTab] = React.useState('in');
  const [selected, setSelected] = React.useState(0);
  const data = {
    in: [{
      subject: 'درخواست خرید مواد اولیه (استامینوفن)',
      from: 'واحد تدارکات',
      pri: ['gold', 'فوری'],
      status: ['warning', 'در انتظار'],
      date: 'امروز ۰۹:۲۴'
    }, {
      subject: 'گزارش کنترل کیفیت سری ۸۸۲',
      from: 'کنترل کیفیت',
      pri: ['neutral', 'عادی'],
      status: ['brand', 'در حال بررسی'],
      date: 'امروز ۰۸:۱۰'
    }, {
      subject: 'ابلاغ دستورالعمل انبار سرد',
      from: 'مدیریت عملیات',
      pri: ['neutral', 'عادی'],
      status: ['success', 'تأیید شده'],
      date: 'دیروز ۱۶:۴۰'
    }, {
      subject: 'صورت‌جلسه کمیته دارویی',
      from: 'دبیرخانه',
      pri: ['teal', 'اطلاع'],
      status: ['warning', 'در انتظار'],
      date: 'دیروز ۱۱:۰۲'
    }, {
      subject: 'پیگیری مجوز صادرات به عراق',
      from: 'واحد بازرگانی',
      pri: ['gold', 'فوری'],
      status: ['brand', 'در حال بررسی'],
      date: '۲ روز پیش'
    }],
    out: [{
      subject: 'پاسخ به استعلام سازمان غذا و دارو',
      from: 'به: سازمان غذا و دارو',
      pri: ['neutral', 'عادی'],
      status: ['success', 'ارسال شد'],
      date: 'دیروز ۱۴:۲۰'
    }, {
      subject: 'ابلاغ بخشنامه مرخصی تابستان',
      from: 'به: کلیه واحدها',
      pri: ['teal', 'اطلاع'],
      status: ['success', 'ارسال شد'],
      date: '۳ روز پیش'
    }],
    draft: [{
      subject: 'پیش‌نویس قرارداد تأمین بسته‌بندی',
      from: 'پیش‌نویس',
      pri: ['neutral', 'عادی'],
      status: ['neutral', 'پیش‌نویس'],
      date: 'ذخیره خودکار'
    }]
  };
  const rows = data[tab];
  const active = rows[selected] || rows[0];
  const cols = [{
    key: 'subject',
    header: 'موضوع',
    render: (v, r) => /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
      style: {
        fontWeight: 600,
        color: 'var(--text-strong)'
      }
    }, v), /*#__PURE__*/React.createElement("div", {
      style: {
        fontSize: 12,
        color: 'var(--text-subtle)',
        marginTop: 2
      }
    }, r.from))
  }, {
    key: 'pri',
    header: 'اولویت',
    render: v => /*#__PURE__*/React.createElement(Tag, {
      tone: v[0]
    }, v[1])
  }, {
    key: 'status',
    header: 'وضعیت',
    render: v => /*#__PURE__*/React.createElement(Badge, {
      tone: v[0],
      dot: true
    }, v[1])
  }, {
    key: 'date',
    header: 'تاریخ',
    align: 'end',
    nowrap: true
  }];
  return /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--container-max)',
      display: 'flex',
      flexDirection: 'column',
      gap: 18
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      font: 'var(--text-h1)',
      color: 'var(--text-strong)'
    }
  }, "\u06A9\u0627\u0631\u062A\u0627\u0628\u0644 \u0645\u0646"), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '6px 0 0',
      color: 'var(--text-muted)'
    }
  }, "\u0645\u062F\u06CC\u0631\u06CC\u062A \u0646\u0627\u0645\u0647\u200C\u0647\u0627 \u0648 \u062F\u0631\u062E\u0648\u0627\u0633\u062A\u200C\u0647\u0627\u06CC \u062F\u0631 \u0627\u0646\u062A\u0638\u0627\u0631 \u0627\u0642\u062F\u0627\u0645")), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    iconStart: /*#__PURE__*/React.createElement(I.plus, {
      size: 18
    }),
    onClick: onCompose
  }, "\u0646\u0627\u0645\u0647 \u062C\u062F\u06CC\u062F")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1.6fr 1fr',
      gap: 18,
      alignItems: 'start'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    padding: false
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '4px 16px 0'
    }
  }, /*#__PURE__*/React.createElement(Tabs, {
    value: tab,
    onChange: t => {
      setTab(t);
      setSelected(0);
    },
    items: [{
      value: 'in',
      label: 'وارده',
      count: '۳۷'
    }, {
      value: 'out',
      label: 'صادره',
      count: '۹'
    }, {
      value: 'draft',
      label: 'پیش‌نویس',
      count: '۱'
    }]
  })), /*#__PURE__*/React.createElement(Table, {
    columns: cols,
    rows: rows,
    onRowClick: (r, i) => setSelected(i)
  })), /*#__PURE__*/React.createElement(Card, {
    accent: "brand",
    style: {
      position: 'sticky',
      top: 92
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'flex-start',
      justifyContent: 'space-between',
      gap: 12,
      marginBottom: 14
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    name: active.from,
    size: 40
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      color: 'var(--text-strong)',
      fontSize: 15
    }
  }, active.subject), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12.5,
      color: 'var(--text-subtle)',
      marginTop: 3
    }
  }, active.from, " \xB7 ", active.date)))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      marginBottom: 16
    }
  }, /*#__PURE__*/React.createElement(Tag, {
    tone: active.pri[0]
  }, active.pri[1]), /*#__PURE__*/React.createElement(Badge, {
    tone: active.status[0],
    dot: true
  }, active.status[1])), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      color: 'var(--text-body)',
      lineHeight: 'var(--lh-normal)',
      fontSize: 14
    }
  }, "\u0628\u0627 \u0633\u0644\u0627\u0645 \u0648 \u0627\u062D\u062A\u0631\u0627\u0645\u060C \u0628\u0647 \u0627\u0633\u062A\u062D\u0636\u0627\u0631 \u0645\u06CC\u200C\u0631\u0633\u0627\u0646\u062F \u062F\u0631 \u0631\u0627\u0633\u062A\u0627\u06CC \u062A\u0623\u0645\u06CC\u0646 \u0628\u0647\u200C\u0645\u0648\u0642\u0639 \u0645\u0648\u0627\u062F \u0627\u0648\u0644\u06CC\u0647\u200C\u06CC \u062E\u0637 \u062A\u0648\u0644\u06CC\u062F\u060C \u062E\u0648\u0627\u0647\u0634\u0645\u0646\u062F \u0627\u0633\u062A \u0646\u0633\u0628\u062A \u0628\u0647 \u0628\u0631\u0631\u0633\u06CC \u0648 \u062A\u0623\u06CC\u06CC\u062F \u062F\u0631\u062E\u0648\u0627\u0633\u062A \u067E\u06CC\u0648\u0633\u062A \u062F\u0631 \u0627\u0633\u0631\u0639 \u0648\u0642\u062A \u0627\u0642\u062F\u0627\u0645 \u0641\u0631\u0645\u0627\u06CC\u06CC\u062F."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8,
      marginTop: 16,
      padding: '10px 12px',
      background: 'var(--surface-sunken)',
      borderRadius: 'var(--r-md)',
      fontSize: 13,
      color: 'var(--text-muted)'
    }
  }, /*#__PURE__*/React.createElement(I.paperclip, {
    size: 16
  }), " \u0641\u0627\u06A9\u062A\u0648\u0631-\u067E\u06CC\u0634\u200C\u0641\u0627\u06A9\u062A\u0648\u0631-\u06F8\u06F8\u06F2.pdf"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 10,
      marginTop: 18
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "gold",
    block: true,
    iconStart: /*#__PURE__*/React.createElement(I.check, {
      size: 17
    })
  }, "\u062A\u0623\u06CC\u06CC\u062F \u0648 \u0627\u0631\u062C\u0627\u0639"), /*#__PURE__*/React.createElement(Button, {
    variant: "secondary",
    block: true
  }, "\u0639\u062F\u0645 \u062A\u0623\u06CC\u06CC\u062F")))));
}
window.CartableScreen = CartableScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/automation/CartableScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/automation/ComposeScreen.jsx
try { (() => {
// ComposeScreen — new letter form (as a modal over the current screen).
function ComposeScreen({
  onClose,
  onSend
}) {
  const {
    Card,
    Input,
    Select,
    Textarea,
    Checkbox,
    Button,
    Tag,
    Alert
  } = window.DesignSystem_e25c50;
  const I = window.AfIcons;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'fixed',
      inset: 0,
      background: 'var(--overlay-scrim)',
      backdropFilter: 'blur(3px)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      padding: 24,
      zIndex: 50
    },
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 'min(720px, 100%)',
      maxHeight: '92vh',
      overflow: 'auto'
    },
    onClick: e => e.stopPropagation()
  }, /*#__PURE__*/React.createElement(Card, {
    title: "\u0627\u06CC\u062C\u0627\u062F \u0646\u0627\u0645\u0647 \u062C\u062F\u06CC\u062F",
    subtitle: "\u0646\u0627\u0645\u0647 \u0627\u062F\u0627\u0631\u06CC \u062F\u0627\u062E\u0644\u06CC",
    accent: "brand",
    actions: /*#__PURE__*/React.createElement(Button, {
      variant: "ghost",
      size: "sm",
      onClick: onClose
    }, "\u0628\u0633\u062A\u0646 \u2715")
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 1fr',
      gap: 14
    }
  }, /*#__PURE__*/React.createElement(Select, {
    label: "\u06AF\u06CC\u0631\u0646\u062F\u0647",
    placeholder: "\u0627\u0646\u062A\u062E\u0627\u0628 \u0648\u0627\u062D\u062F \u06CC\u0627 \u0634\u062E\u0635",
    options: ['مدیریت عملیات', 'واحد مالی', 'کنترل کیفیت', 'واحد بازرگانی', 'دبیرخانه']
  }), /*#__PURE__*/React.createElement(Select, {
    label: "\u0627\u0648\u0644\u0648\u06CC\u062A",
    defaultValue: "\u0639\u0627\u062F\u06CC",
    options: ['فوری', 'عادی', 'اطلاع']
  })), /*#__PURE__*/React.createElement(Input, {
    label: "\u0645\u0648\u0636\u0648\u0639",
    placeholder: "\u0645\u0648\u0636\u0648\u0639 \u0646\u0627\u0645\u0647 \u0631\u0627 \u0648\u0627\u0631\u062F \u06A9\u0646\u06CC\u062F",
    required: true
  }), /*#__PURE__*/React.createElement(Textarea, {
    label: "\u0645\u062A\u0646 \u0646\u0627\u0645\u0647",
    rows: 6,
    placeholder: "\u0628\u0627 \u0633\u0644\u0627\u0645 \u0648 \u0627\u062D\u062A\u0631\u0627\u0645\u2026"
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      font: 'var(--text-label)',
      color: 'var(--text-body)',
      marginBottom: 8
    }
  }, "\u067E\u06CC\u0648\u0633\u062A\u200C\u0647\u0627"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      alignItems: 'center',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement(Tag, {
    tone: "teal",
    icon: /*#__PURE__*/React.createElement(I.paperclip, {
      size: 14
    }),
    onRemove: () => {}
  }, "\u0641\u0627\u06A9\u062A\u0648\u0631-\u06F8\u06F8\u06F2.pdf"), /*#__PURE__*/React.createElement(Button, {
    variant: "secondary",
    size: "sm",
    iconStart: /*#__PURE__*/React.createElement(I.plus, {
      size: 16
    })
  }, "\u0627\u0641\u0632\u0648\u062F\u0646 \u0641\u0627\u06CC\u0644"))), /*#__PURE__*/React.createElement(Alert, {
    tone: "info"
  }, "\u0627\u06CC\u0646 \u0646\u0627\u0645\u0647 \u067E\u0633 \u0627\u0632 \u0627\u0631\u0633\u0627\u0644\u060C \u0648\u0627\u0631\u062F \u06AF\u0631\u062F\u0634 \u06A9\u0627\u0631 \xAB\u062A\u0623\u06CC\u06CC\u062F \u0645\u062F\u06CC\u0631 \u0648\u0627\u062D\u062F\xBB \u062E\u0648\u0627\u0647\u062F \u0634\u062F."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 12,
      borderTop: '1px solid var(--border-subtle)',
      paddingTop: 16
    }
  }, /*#__PURE__*/React.createElement(Checkbox, {
    label: "\u0646\u06CC\u0627\u0632 \u0628\u0647 \u062A\u0623\u06CC\u06CC\u062F \u0645\u062F\u06CC\u0631",
    defaultChecked: true
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "ghost",
    onClick: onClose
  }, "\u0630\u062E\u06CC\u0631\u0647 \u067E\u06CC\u0634\u200C\u0646\u0648\u06CC\u0633"), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    iconStart: /*#__PURE__*/React.createElement(I.send, {
      size: 17
    }),
    onClick: onSend
  }, "\u0627\u0631\u0633\u0627\u0644 \u0646\u0627\u0645\u0647")))))));
}
window.ComposeScreen = ComposeScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/automation/ComposeScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/automation/DashboardScreen.jsx
try { (() => {
// DashboardScreen — KPI overview + recent cartable + workflow progress.
function DashboardScreen({
  onOpenCartable,
  onCompose
}) {
  const {
    StatCard,
    Card,
    Table,
    Badge,
    Avatar,
    Button,
    ProgressBar
  } = window.DesignSystem_e25c50;
  const I = window.AfIcons;
  const recent = [{
    subject: 'درخواست خرید مواد اولیه (استامینوفن)',
    from: 'واحد تدارکات',
    status: ['warning', 'در انتظار'],
    date: 'امروز ۰۹:۲۴'
  }, {
    subject: 'گزارش کنترل کیفیت سری ۸۸۲',
    from: 'کنترل کیفیت',
    status: ['success', 'تأیید شده'],
    date: 'دیروز ۱۶:۱۰'
  }, {
    subject: 'ابلاغ دستورالعمل انبار سرد',
    from: 'مدیریت عملیات',
    status: ['brand', 'در حال بررسی'],
    date: 'دیروز ۱۱:۰۲'
  }, {
    subject: 'درخواست مرخصی استحقاقی',
    from: 'محمد رضایی',
    status: ['danger', 'رد شده'],
    date: '۲ روز پیش'
  }];
  const cols = [{
    key: 'subject',
    header: 'موضوع نامه'
  }, {
    key: 'from',
    header: 'فرستنده',
    render: v => /*#__PURE__*/React.createElement("span", {
      style: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: 8
      }
    }, /*#__PURE__*/React.createElement(Avatar, {
      name: v,
      size: 26
    }), v)
  }, {
    key: 'status',
    header: 'وضعیت',
    render: v => /*#__PURE__*/React.createElement(Badge, {
      tone: v[0],
      dot: true
    }, v[1])
  }, {
    key: 'date',
    header: 'تاریخ',
    align: 'end',
    nowrap: true
  }];
  const workflows = [{
    name: 'تأیید فاکتور خرید',
    pct: 80,
    tone: 'brand'
  }, {
    name: 'صدور مجوز تولید',
    pct: 55,
    tone: 'teal'
  }, {
    name: 'بازبینی اسناد صادرات',
    pct: 32,
    tone: 'gold'
  }];
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 22,
      maxWidth: 'var(--container-max)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'flex-end',
      justifyContent: 'space-between',
      gap: 16,
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      font: 'var(--text-h1)',
      color: 'var(--text-strong)'
    }
  }, "\u0633\u0644\u0627\u0645 \u0632\u0647\u0631\u0627 \uD83D\uDC4B"), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '6px 0 0',
      color: 'var(--text-muted)'
    }
  }, "\u0627\u0645\u0631\u0648\u0632 \u06F3\u06F7 \u0646\u0627\u0645\u0647 \u062F\u0631 \u06A9\u0627\u0631\u062A\u0627\u0628\u0644 \u0634\u0645\u0627 \u062F\u0631 \u0627\u0646\u062A\u0638\u0627\u0631 \u0627\u0642\u062F\u0627\u0645 \u0627\u0633\u062A.")), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    iconStart: /*#__PURE__*/React.createElement(I.plus, {
      size: 18
    }),
    onClick: onCompose
  }, "\u0646\u0627\u0645\u0647 \u062C\u062F\u06CC\u062F")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(4, 1fr)',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement(StatCard, {
    tone: "teal",
    icon: /*#__PURE__*/React.createElement(I.inbox, null),
    value: "\u06F3\u06F7",
    label: "\u062F\u0631 \u0627\u0646\u062A\u0638\u0627\u0631 \u0627\u0642\u062F\u0627\u0645",
    delta: "\u06F1\u06F2\u066A",
    deltaDir: "up"
  }), /*#__PURE__*/React.createElement(StatCard, {
    tone: "brand",
    icon: /*#__PURE__*/React.createElement(I.send, null),
    value: "\u06F1\u066C\u06F2\u06F4\u06F8",
    label: "\u0646\u0627\u0645\u0647 \u0627\u0631\u0633\u0627\u0644\u06CC \u0627\u06CC\u0646 \u0645\u0627\u0647",
    delta: "\u06F8\u066A",
    deltaDir: "up"
  }), /*#__PURE__*/React.createElement(StatCard, {
    tone: "gold",
    icon: /*#__PURE__*/React.createElement(I.clock, null),
    value: "\u06F5",
    label: "\u0646\u0632\u062F\u06CC\u06A9 \u0645\u0647\u0644\u062A",
    delta: "\u06F2",
    deltaDir: "down"
  }), /*#__PURE__*/React.createElement(StatCard, {
    tone: "neutral",
    icon: /*#__PURE__*/React.createElement(I.check, null),
    value: "\u06F9\u06F4\u066A",
    label: "\u0646\u0631\u062E \u067E\u0627\u0633\u062E \u0628\u0647\u200C\u0645\u0648\u0642\u0639",
    delta: "\u06F0\u066A",
    deltaDir: "flat"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '2fr 1fr',
      gap: 16,
      alignItems: 'start'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "\u0622\u062E\u0631\u06CC\u0646 \u0646\u0627\u0645\u0647\u200C\u0647\u0627\u06CC \u06A9\u0627\u0631\u062A\u0627\u0628\u0644",
    subtitle: "\u06F4 \u0645\u0648\u0631\u062F \u0627\u062E\u06CC\u0631",
    padding: false,
    actions: /*#__PURE__*/React.createElement(Button, {
      variant: "ghost",
      size: "sm",
      onClick: onOpenCartable
    }, "\u0645\u0634\u0627\u0647\u062F\u0647 \u0647\u0645\u0647")
  }, /*#__PURE__*/React.createElement(Table, {
    columns: cols,
    rows: recent,
    onRowClick: onOpenCartable
  })), /*#__PURE__*/React.createElement(Card, {
    title: "\u06AF\u0631\u062F\u0634 \u06A9\u0627\u0631\u0647\u0627\u06CC \u0641\u0639\u0627\u0644",
    accent: "teal"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 18
    }
  }, workflows.map(w => /*#__PURE__*/React.createElement("div", {
    key: w.name
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      marginBottom: 8,
      fontSize: 13.5
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--text-body)'
    }
  }, w.name), /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--text-subtle)'
    },
    className: "num"
  }, w.pct, "\u066A")), /*#__PURE__*/React.createElement(ProgressBar, {
    value: w.pct,
    tone: w.tone
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      borderTop: '1px solid var(--border-subtle)',
      paddingTop: 14,
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      color: 'var(--text-muted)',
      fontSize: 13
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--teal-600)',
      display: 'flex'
    }
  }, /*#__PURE__*/React.createElement(I.flask, {
    size: 18
  })), "\u0645\u06CC\u0627\u0646\u06AF\u06CC\u0646 \u0632\u0645\u0627\u0646 \u062A\u06A9\u0645\u06CC\u0644: \u06F2\u066B\u06F4 \u0631\u0648\u0632")))));
}
window.DashboardScreen = DashboardScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/automation/DashboardScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/automation/LoginScreen.jsx
try { (() => {
// LoginScreen — recreation of the آرتا فارما automation login (from brand screenshot).
function LoginScreen({
  onLogin
}) {
  const {
    Input,
    Button,
    Checkbox
  } = window.DesignSystem_e25c50;
  const I = window.AfIcons;
  const [user, setUser] = React.useState('');
  const [pass, setPass] = React.useState('');
  return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: '100%',
      display: 'flex',
      flexDirection: 'column',
      background: 'var(--grad-brand)',
      position: 'relative',
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: -120,
      insetInlineStart: -80,
      width: 460,
      height: 460,
      borderRadius: '50%',
      background: 'radial-gradient(circle, rgba(255,255,255,0.22), transparent 65%)',
      pointerEvents: 'none'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      bottom: -160,
      insetInlineEnd: 120,
      width: 520,
      height: 520,
      borderRadius: '50%',
      background: 'radial-gradient(circle, rgba(0,174,195,0.28), transparent 65%)',
      pointerEvents: 'none'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      display: 'grid',
      gridTemplateColumns: '1fr 1fr',
      alignItems: 'center',
      gap: 40,
      padding: '48px 72px',
      position: 'relative',
      zIndex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: '100%',
      maxWidth: 420,
      background: 'var(--glass-bg)',
      border: '1px solid var(--glass-border)',
      backdropFilter: 'var(--glass-blur)',
      WebkitBackdropFilter: 'var(--glass-blur)',
      borderRadius: 'var(--r-2xl)',
      padding: '38px 34px',
      boxShadow: 'var(--shadow-xl)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'center',
      marginBottom: 28
    }
  }, /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      font: 'var(--text-h1)',
      color: '#fff'
    }
  }, "\u0648\u0631\u0648\u062F \u06A9\u0627\u0631\u0628\u0631\u0627\u0646"), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '8px 0 0',
      fontSize: 'var(--fs-body)',
      color: 'rgba(255,255,255,0.78)'
    }
  }, "\u0633\u0627\u0645\u0627\u0646\u0647 \u06CC\u06A9\u067E\u0627\u0631\u0686\u0647 \u0627\u062A\u0648\u0645\u0627\u0633\u06CC\u0648\u0646 \u0627\u062F\u0627\u0631\u06CC")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "af-login-field"
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "\u0646\u0627\u0645 \u06A9\u0627\u0631\u0628\u0631\u06CC",
    icon: /*#__PURE__*/React.createElement(I.user, {
      size: 18
    }),
    value: user,
    onChange: e => setUser(e.target.value)
  })), /*#__PURE__*/React.createElement("div", {
    className: "af-login-field"
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "\u0631\u0645\u0632 \u0639\u0628\u0648\u0631",
    type: "password",
    icon: /*#__PURE__*/React.createElement(I.lock, {
      size: 18
    }),
    value: pass,
    onChange: e => setPass(e.target.value)
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      '--af-check': 1
    }
  }, /*#__PURE__*/React.createElement(Checkbox, {
    label: "\u0645\u0631\u0627 \u0628\u0647 \u062E\u0627\u0637\u0631 \u0628\u0633\u067E\u0627\u0631",
    defaultChecked: true,
    style: {
      color: '#fff'
    }
  })), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      fontSize: 'var(--fs-sm)',
      color: 'var(--gold-300)',
      textDecoration: 'none',
      fontWeight: 500
    }
  }, "\u0641\u0631\u0627\u0645\u0648\u0634\u06CC \u0631\u0645\u0632\u061F")), /*#__PURE__*/React.createElement(Button, {
    variant: "gold",
    size: "lg",
    block: true,
    onClick: onLogin,
    iconStart: /*#__PURE__*/React.createElement(I.chevronLeft, {
      size: 18
    })
  }, "\u0648\u0631\u0648\u062F \u0628\u0647 \u0633\u0627\u0645\u0627\u0646\u0647")))), /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/logo-full-white.png",
    alt: "\u06AF\u0631\u0648\u0647 \u062F\u0627\u0631\u0648\u0633\u0627\u0632\u06CC \u0622\u0631\u062A\u0627 \u0641\u0627\u0631\u0645\u0627",
    style: {
      width: 'min(460px, 82%)'
    }
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'rgba(9,24,58,0.34)',
      backdropFilter: 'blur(6px)',
      borderTop: '1px solid rgba(255,255,255,0.14)',
      padding: '14px 40px',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      position: 'relative',
      zIndex: 1
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-sm)',
      color: 'rgba(255,255,255,0.7)'
    }
  }, "\xA9 \u06F1\u06F4\u06F0\u06F3 \u06AF\u0631\u0648\u0647 \u062F\u0627\u0631\u0648\u0633\u0627\u0632\u06CC \u0622\u0631\u062A\u0627 \u0641\u0627\u0631\u0645\u0627 \xB7 \u0622\u0631\u062A\u0627 \u0642\u0644\u0628 \u0628\u0647\u0633\u0627\u0646 \u0637\u0628"), /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 8,
      color: '#fff',
      fontSize: 'var(--fs-sm)'
    }
  }, /*#__PURE__*/React.createElement(I.globe, {
    size: 17
  }), " \u0641\u0627\u0631\u0633\u06CC")));
}
window.LoginScreen = LoginScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/automation/LoginScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/automation/Shell.jsx
try { (() => {
// Shell — app chrome: RTL sidebar rail + top bar. Wraps every logged-in screen.
function Shell({
  active,
  onNav,
  onLogout,
  children
}) {
  const {
    SidebarItem,
    Avatar,
    IconButton,
    Input
  } = window.DesignSystem_e25c50;
  const I = window.AfIcons;
  const nav = [{
    key: 'dashboard',
    label: 'داشبورد',
    icon: /*#__PURE__*/React.createElement(I.home, {
      size: 18
    })
  }, {
    key: 'cartable',
    label: 'کارتابل من',
    icon: /*#__PURE__*/React.createElement(I.inbox, {
      size: 18
    }),
    count: '۳۷'
  }, {
    key: 'letters',
    label: 'نامه‌ها',
    icon: /*#__PURE__*/React.createElement(I.file, {
      size: 18
    }),
    count: '۱۲'
  }, {
    key: 'automation',
    label: 'گردش کارها',
    icon: /*#__PURE__*/React.createElement(I.chart, {
      size: 18
    })
  }, {
    key: 'personnel',
    label: 'کارکنان',
    icon: /*#__PURE__*/React.createElement(I.users, {
      size: 18
    })
  }, {
    key: 'archive',
    label: 'بایگانی',
    icon: /*#__PURE__*/React.createElement(I.archive, {
      size: 18
    })
  }];
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'var(--sidebar-w) 1fr',
      minHeight: '100%',
      background: 'var(--bg-app)'
    }
  }, /*#__PURE__*/React.createElement("aside", {
    style: {
      background: 'var(--surface-card)',
      borderInlineStart: '1px solid var(--border-subtle)',
      display: 'flex',
      flexDirection: 'column',
      padding: '18px 14px',
      gap: 4,
      position: 'sticky',
      top: 0,
      height: '100vh'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 11,
      padding: '6px 8px 18px'
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/logo-mark.png",
    alt: "\u0622\u0631\u062A\u0627 \u0641\u0627\u0631\u0645\u0627",
    style: {
      height: 40
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      lineHeight: 1.3
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 800,
      fontSize: 15,
      color: 'var(--brand-strong)'
    }
  }, "\u0622\u0631\u062A\u0627 \u0641\u0627\u0631\u0645\u0627"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11,
      color: 'var(--text-subtle)'
    }
  }, "\u0627\u062A\u0648\u0645\u0627\u0633\u06CC\u0648\u0646 \u0627\u062F\u0627\u0631\u06CC"))), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11,
      fontWeight: 600,
      color: 'var(--text-subtle)',
      padding: '6px 10px 4px'
    }
  }, "\u0645\u0646\u0648\u06CC \u0627\u0635\u0644\u06CC"), nav.map(n => /*#__PURE__*/React.createElement(SidebarItem, {
    key: n.key,
    icon: n.icon,
    label: n.label,
    count: n.count,
    active: active === n.key,
    onClick: () => onNav(n.key)
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 'auto',
      display: 'flex',
      flexDirection: 'column',
      gap: 4
    }
  }, /*#__PURE__*/React.createElement(SidebarItem, {
    icon: /*#__PURE__*/React.createElement(I.cog, {
      size: 18
    }),
    label: "\u062A\u0646\u0638\u06CC\u0645\u0627\u062A",
    active: active === 'settings',
    onClick: () => onNav('settings')
  }), /*#__PURE__*/React.createElement(SidebarItem, {
    icon: /*#__PURE__*/React.createElement(I.logout, {
      size: 18
    }),
    label: "\u062E\u0631\u0648\u062C",
    onClick: onLogout
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("header", {
    style: {
      height: 66,
      background: 'var(--surface-card)',
      borderBottom: '1px solid var(--border-subtle)',
      display: 'flex',
      alignItems: 'center',
      gap: 16,
      padding: '0 26px',
      position: 'sticky',
      top: 0,
      zIndex: 5
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      maxWidth: 380
    }
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "\u062C\u0633\u062A\u062C\u0648\u06CC \u0646\u0627\u0645\u0647\u060C \u0634\u062E\u0635 \u06CC\u0627 \u0634\u0646\u0627\u0633\u0647\u2026",
    icon: /*#__PURE__*/React.createElement(I.search, {
      size: 17
    })
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), /*#__PURE__*/React.createElement(IconButton, {
    label: "\u0627\u0639\u0644\u0627\u0646\u200C\u0647\u0627",
    variant: "ghost"
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'relative',
      display: 'flex'
    }
  }, /*#__PURE__*/React.createElement(I.bell, {
    size: 20
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      top: -2,
      insetInlineEnd: -2,
      width: 8,
      height: 8,
      borderRadius: '50%',
      background: 'var(--danger-500)',
      border: '2px solid var(--surface-card)'
    }
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      width: 1,
      height: 28,
      background: 'var(--border-subtle)'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    name: "\u0632\u0647\u0631\u0627 \u06A9\u0631\u06CC\u0645\u06CC",
    status: "online",
    size: 38
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      lineHeight: 1.3
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 600,
      fontSize: 13.5,
      color: 'var(--text-strong)'
    }
  }, "\u0632\u0647\u0631\u0627 \u06A9\u0631\u06CC\u0645\u06CC"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11.5,
      color: 'var(--text-subtle)'
    }
  }, "\u06A9\u0627\u0631\u0634\u0646\u0627\u0633 \u0645\u0627\u0644\u06CC")))), /*#__PURE__*/React.createElement("main", {
    style: {
      flex: 1,
      padding: '26px',
      overflowY: 'auto'
    }
  }, children)));
}
window.Shell = Shell;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/automation/Shell.jsx", error: String((e && e.message) || e) }); }

// ui_kits/automation/icons.jsx
try { (() => {
// Shared Lucide-style icons for the آرتا فارما automation kit.
// SUBSTITUTION: Lucide icon paths (MIT) stand in for the product's own glyphs.
const _icn = (paths, sw = 2) => ({
  size = 20,
  style
} = {}) => React.createElement('svg', {
  width: size,
  height: size,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: sw,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  style
}, paths.map((d, i) => React.createElement('path', {
  key: i,
  d
})));
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
  flask: _icn(['M9 3h6', 'M10 3v6.6a2 2 0 0 1-.3 1L5 20a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-4.7-9.4a2 2 0 0 1-.3-1V3', 'M6.5 15h11'])
};
window.AfIcons = Icons;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/automation/icons.jsx", error: String((e && e.message) || e) }); }

__ds_ns.Table = __ds_scope.Table;

__ds_ns.Avatar = __ds_scope.Avatar;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.StatCard = __ds_scope.StatCard;

__ds_ns.Tag = __ds_scope.Tag;

__ds_ns.Alert = __ds_scope.Alert;

__ds_ns.ProgressBar = __ds_scope.ProgressBar;

__ds_ns.WorkflowStepper = __ds_scope.WorkflowStepper;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Checkbox = __ds_scope.Checkbox;

__ds_ns.IconButton = __ds_scope.IconButton;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.Select = __ds_scope.Select;

__ds_ns.Switch = __ds_scope.Switch;

__ds_ns.Textarea = __ds_scope.Textarea;

__ds_ns.SidebarItem = __ds_scope.SidebarItem;

__ds_ns.SidebarNav = __ds_scope.SidebarNav;

__ds_ns.Tabs = __ds_scope.Tabs;

})();
