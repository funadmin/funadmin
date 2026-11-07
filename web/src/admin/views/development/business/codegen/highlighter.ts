import type { HighlighterCore } from 'shiki/core';

export type CodeLanguage = 'php' | 'sql' | 'typescript' | 'vue' | 'json' | 'text';

export const CODE_THEME = 'github-dark';

const HTML_ESCAPES: Record<string, string> = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;'
};

export function escapeHtml(value: string): string {
  return value.replace(/[&<>"']/g, (char) => HTML_ESCAPES[char]);
}

let highlighterPromise: Promise<HighlighterCore> | null = null;

/**
 * Lazily creates a single Shiki highlighter (JS regex engine, no wasm). All Shiki modules are
 * dynamically imported on first use so nothing is pulled into the main bundle.
 */
function getHighlighter(): Promise<HighlighterCore> {
  if (!highlighterPromise) {
    highlighterPromise = (async () => {
      const [{ createHighlighterCore }, { createJavaScriptRegexEngine }] = await Promise.all([
        import('shiki/core'),
        import('shiki/engine/javascript')
      ]);
      return createHighlighterCore({
        engine: createJavaScriptRegexEngine({ forgiving: true }),
        themes: [import('shiki/themes/github-dark.mjs')],
        langs: [
          import('shiki/langs/php.mjs'),
          import('shiki/langs/sql.mjs'),
          import('shiki/langs/typescript.mjs'),
          import('shiki/langs/vue.mjs'),
          import('shiki/langs/json.mjs')
        ]
      });
    })().catch((error: unknown) => {
      // Allow a later call to retry instead of caching the failure forever.
      highlighterPromise = null;
      throw error;
    });
  }
  return highlighterPromise;
}

/**
 * Returns Shiki HTML (a `<pre class="shiki">` block) for code languages, or the escaped source for `text`.
 * Rejects if the highlighter cannot be loaded; callers should fall back to escaped text.
 */
export async function highlight(code: string, lang: CodeLanguage): Promise<string> {
  if (lang === 'text') return escapeHtml(code);
  const highlighter = await getHighlighter();
  return highlighter.codeToHtml(code, { lang, theme: CODE_THEME });
}
