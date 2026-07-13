/**
 * Translucent tint of a token colour — the design's tile / pill / avatar fill.
 * Lives outside Panel.jsx so that file only exports components (react-refresh).
 */
export function tint(color, pct)
{
  return `color-mix(in srgb, ${color} ${pct}%, transparent)`
}
