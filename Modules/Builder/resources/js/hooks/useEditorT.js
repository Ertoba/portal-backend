import { useTranslation } from 'react-i18next'

/**
 * Editor-panel translation helper for the Builder setup UI.
 *
 * The setup editor has ~150 short labels; rather than invent a dotted key for
 * each, we use the English string itself as the natural-language key. The
 * English copy stays inline in the components (readable, and the default when
 * no translation exists), and only Arabic/French values live in the locale
 * files under a flat map.
 *
 * `keySeparator`/`nsSeparator` are disabled per-call so labels containing "."
 * or ":" (placeholders, sentences) are looked up literally instead of being
 * treated as nested paths.
 *
 * Usage:  const et = useEditorT();  <h3>{et('Theme Color')}</h3>
 */
export function useEditorT() {
  const { t } = useTranslation()
  return (text) =>
    t(text, { defaultValue: text, keySeparator: false, nsSeparator: false })
}
