/**
 * Detects whether the given text is predominantly Right-To-Left (RTL, e.g., Farsi/Arabic)
 * or Left-To-Right (LTR, e.g., English).
 * 
 * @param {string} text 
 * @returns {'rtl' | 'ltr'}
 */
export function detectDirection(text) {
    if (!text || typeof text !== 'string') {
        return 'ltr';
    }

    // Strip code blocks, numbers, whitespace, and punctuation
    const cleanText = text
        .replace(/```[\s\S]*?```/g, '')
        .replace(/`[^`]*`/g, '')
        .replace(/[\d\s\p{P}\p{S}]/gu, '');

    if (!cleanText) {
        return 'ltr';
    }

    // Unicode ranges for Arabic, Persian, Kurdish, Hebrew, etc.
    const rtlRegex = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFE]/g;
    const rtlMatches = cleanText.match(rtlRegex) || [];

    const ltrRegex = /[a-zA-Z]/g;
    const ltrMatches = cleanText.match(ltrRegex) || [];

    if (rtlMatches.length === 0 && ltrMatches.length === 0) {
        return 'ltr';
    }

    return rtlMatches.length >= ltrMatches.length ? 'rtl' : 'ltr';
}
