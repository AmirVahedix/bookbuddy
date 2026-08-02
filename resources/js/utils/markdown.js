import katex from 'katex';

/**
 * Markdown & Math Formula Renderer for BookBuddy
 */
export const renderMarkdown = (text) => {
    if (!text) return '';

    const codeTokens = [];
    const mathTokens = [];

    let processed = text;

    // 1. Protect Code Blocks first (so math tokens inside code blocks are ignored)
    processed = processed.replace(/```(\w*)\n?([\s\S]*?)\n?```/g, (match, lang, code) => {
        const index = codeTokens.length;
        // Escape HTML inside code block
        const escapedCode = code
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        codeTokens.push({ lang, code: escapedCode });
        return `___CODE_BLOCK_${index}___`;
    });

    // 2. Protect Block / Display Math ($$...$$ and \[...\])
    processed = processed.replace(/\$\$([\s\S]*?)\$\$/g, (match, tex) => {
        const index = mathTokens.length;
        mathTokens.push({ tex: tex.trim(), display: true });
        return `___MATH_BLOCK_${index}___`;
    });

    processed = processed.replace(/\\\[([\s\S]*?)\\\]/g, (match, tex) => {
        const index = mathTokens.length;
        mathTokens.push({ tex: tex.trim(), display: true });
        return `___MATH_BLOCK_${index}___`;
    });

    // 3. Protect Inline Math (\(...html\) and $...$)
    processed = processed.replace(/\\\(([\s\S]*?)\\\)/g, (match, tex) => {
        const index = mathTokens.length;
        mathTokens.push({ tex: tex.trim(), display: false });
        return `___MATH_INLINE_${index}___`;
    });

    processed = processed.replace(/\$([^\$\n]+?)\$/g, (match, tex) => {
        // Simple check to avoid replacing solitary currency amounts like $100 if it's strictly numerical without math operators/variables
        const trimmed = tex.trim();
        if (/^\d+(\.\d+)?$/.test(trimmed)) {
            return match;
        }
        const index = mathTokens.length;
        mathTokens.push({ tex: trimmed, display: false });
        return `___MATH_INLINE_${index}___`;
    });

    // 4. Escape remaining HTML
    let escaped = processed
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // 5. Line-by-line Markdown parsing
    const lines = escaped.split('\n');
    let htmlResult = [];
    let inList = false;
    let listType = ''; // 'ul' or 'ol'
    let inTable = false;
    let tableRows = [];

    const closeList = () => {
        if (inList) {
            htmlResult.push(`</${listType}>`);
            inList = false;
            listType = '';
        }
    };

    const closeTable = () => {
        if (inTable) {
            let tableHtml = '<div class="overflow-x-auto my-6 border border-slate-200 dark:border-slate-800 rounded-xl"><table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">';
            let hasHeader = false;
            
            let separatorIndex = -1;
            if (tableRows.length > 1) {
                const secondRow = tableRows[1].trim();
                if (/^\|?\s*:?-+:?\s*(\|?\s*:?-+:?\s*)*\|?$/.test(secondRow)) {
                    separatorIndex = 1;
                    hasHeader = true;
                }
            }

            for (let i = 0; i < tableRows.length; i++) {
                if (i === separatorIndex) continue;
                
                const rowStr = tableRows[i].trim();
                const cleanRow = rowStr.replace(/^\|/, '').replace(/\|$/, '');
                const cells = cleanRow.split('|').map(c => c.trim());

                if (hasHeader && i === 0) {
                    tableHtml += '<thead class="bg-slate-50 dark:bg-slate-900/50"><tr>';
                    cells.forEach(cell => {
                        tableHtml += `<th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-350">${cell}</th>`;
                    });
                    tableHtml += '</tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-transparent">';
                } else {
                    if (i === 0 || (i === 1 && separatorIndex === -1)) {
                        tableHtml += '<tbody class="divide-y divide-slate-200 dark:divide-slate-800">';
                    }
                    tableHtml += '<tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/10">';
                    cells.forEach(cell => {
                        tableHtml += `<td class="px-4 py-3 text-slate-700 dark:text-slate-300 font-medium">${cell}</td>`;
                    });
                    tableHtml += '</tr>';
                }
            }
            tableHtml += '</tbody></table></div>';
            htmlResult.push(tableHtml);
            inTable = false;
            tableRows = [];
        }
    };

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const trimmed = line.trim();

        // Check for Code Block Token
        if (trimmed.startsWith('___CODE_BLOCK_')) {
            closeList();
            closeTable();
            htmlResult.push(trimmed);
            continue;
        }

        // Check for Standalone Block Math Token
        if (/^___MATH_BLOCK_\d+___$/.test(trimmed)) {
            closeList();
            closeTable();
            htmlResult.push(`<div class="math-display my-4 text-center overflow-x-auto py-2 text-slate-900 dark:text-slate-100 font-sans">${trimmed}</div>`);
            continue;
        }

        // Headings
        if (trimmed.startsWith('#')) {
            closeList();
            closeTable();
            if (trimmed.startsWith('### ')) {
                htmlResult.push(`<h4 class="text-sm font-bold text-slate-900 dark:text-slate-100 mt-6 mb-2">${trimmed.substring(4)}</h4>`);
            } else if (trimmed.startsWith('## ')) {
                htmlResult.push(`<h3 class="text-base font-extrabold text-slate-900 dark:text-white mt-8 mb-3 border-l-3 border-violet-500 pl-3">${trimmed.substring(3)}</h3>`);
            } else if (trimmed.startsWith('# ')) {
                htmlResult.push(`<h2 class="text-lg font-black text-violet-600 dark:text-violet-400 mt-10 mb-4 border-b border-slate-200 dark:border-slate-800/80 pb-2">${trimmed.substring(2)}</h2>`);
            }
            continue;
        }

        // Blockquotes
        if (trimmed.startsWith('&gt;')) {
            closeList();
            closeTable();
            const quoteContent = trimmed.substring(4).trim();
            htmlResult.push(`<blockquote class="border-l-4 border-slate-350 dark:border-slate-700 pl-4 py-1 my-3 text-slate-500 dark:text-slate-400 italic">${quoteContent}</blockquote>`);
            continue;
        }

        // Tables detection
        if (trimmed.startsWith('|')) {
            closeList();
            inTable = true;
            tableRows.push(line);
            continue;
        } else if (inTable && !trimmed.startsWith('|')) {
            closeTable();
        }

        // Lists (Unordered and Ordered)
        const ulMatch = trimmed.match(/^[\-\*]\s+(.*)$/);
        const olMatch = trimmed.match(/^(\d+)\.\s+(.*)$/);

        if (ulMatch) {
            closeTable();
            if (!inList || listType !== 'ul') {
                closeList();
                inList = true;
                listType = 'ul';
                htmlResult.push('<ul class="my-4 space-y-1">');
            }
            htmlResult.push(`<li class="ml-5 list-disc text-slate-700 dark:text-slate-300">${ulMatch[1]}</li>`);
            continue;
        } else if (olMatch) {
            closeTable();
            if (!inList || listType !== 'ol') {
                closeList();
                inList = true;
                listType = 'ol';
                htmlResult.push('<ol class="my-4 space-y-1">');
            }
            htmlResult.push(`<li class="ml-5 list-decimal text-slate-700 dark:text-slate-300">${olMatch[2]}</li>`);
            continue;
        } else if (inList) {
            closeList();
        }

        // Blank lines
        if (trimmed === '') {
            continue;
        }

        // Regular paragraph
        closeList();
        closeTable();
        htmlResult.push(`<p class="my-4 text-slate-750 dark:text-slate-300 leading-relaxed">${line}</p>`);
    }

    closeList();
    closeTable();

    let finalHtml = htmlResult.join('\n');

    // Inline formatting: Bold, Italic, Code
    finalHtml = finalHtml
        .replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-950 dark:text-white">$1</strong>')
        .replace(/\*(.*?)\*(?!\*)/g, '<em class="italic text-slate-600 dark:text-slate-400">$1</em>')
        .replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-900 text-pink-600 dark:text-pink-400 rounded text-xs font-mono">$1</code>');

    // 6. Restore Math Tokens (Render with KaTeX)
    mathTokens.forEach((token, index) => {
        let renderedHtml = token.tex;
        try {
            renderedHtml = katex.renderToString(token.tex, {
                displayMode: token.display,
                throwOnError: false,
            });
        } catch (e) {
            console.error('KaTeX rendering error:', e);
        }

        const placeholder = token.display ? `___MATH_BLOCK_${index}___` : `___MATH_INLINE_${index}___`;
        finalHtml = finalHtml.replaceAll(placeholder, renderedHtml);
    });

    // 7. Restore Code Block Tokens
    codeTokens.forEach((token, index) => {
        const codeBlockHtml = `<pre class="bg-slate-900 dark:bg-slate-950 text-slate-150 p-4 rounded-xl font-mono text-xs overflow-auto my-4 border border-slate-800/80"><code>${token.code}</code></pre>`;
        finalHtml = finalHtml.replace(`___CODE_BLOCK_${index}___`, codeBlockHtml);
    });

    return finalHtml;
};
