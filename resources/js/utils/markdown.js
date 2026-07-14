/**
 * Simple Markdown Renderer for book summaries
 */
export const renderMarkdown = (text) => {
    if (!text) return '';
    
    // Escape HTML first
    let escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // Code blocks replacement
    escaped = escaped.replace(/```(\w*)\n([\s\S]*?)\n```/g, '<pre class="bg-slate-900 dark:bg-slate-950 text-slate-150 p-4 rounded-xl font-mono text-xs overflow-auto my-4 border border-slate-800/80"><code>$2</code></pre>');
    
    // Split into lines to process lists, tables, headers, and paragraphs
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
            // Process the table rows
            let tableHtml = '<div class="overflow-x-auto my-6 border border-slate-200 dark:border-slate-800 rounded-xl"><table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">';
            let hasHeader = false;
            
            // Check if second row is a separator line like |---|---|
            let separatorIndex = -1;
            if (tableRows.length > 1) {
                const secondRow = tableRows[1].trim();
                if (/^\|?\s*:?-+:?\s*(\|?\s*:?-+:?\s*)*\|?$/.test(secondRow)) {
                    separatorIndex = 1;
                    hasHeader = true;
                }
            }

            for (let i = 0; i < tableRows.length; i++) {
                if (i === separatorIndex) continue; // skip separator row
                
                const rowStr = tableRows[i].trim();
                // strip leading and trailing pipes
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

        // 1. Preformatted code blocks that were already replaced in escaped
        if (trimmed.startsWith('<pre')) {
            closeList();
            closeTable();
            htmlResult.push(line);
            continue;
        }

        // 2. Headings
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

        // 3. Blockquotes
        if (trimmed.startsWith('&gt;')) {
            closeList();
            closeTable();
            const quoteContent = trimmed.substring(4).trim();
            htmlResult.push(`<blockquote class="border-l-4 border-slate-350 dark:border-slate-700 pl-4 py-1 my-3 text-slate-500 dark:text-slate-400 italic">${quoteContent}</blockquote>`);
            continue;
        }

        // 4. Tables detection
        if (trimmed.startsWith('|')) {
            closeList();
            inTable = true;
            tableRows.push(line);
            continue;
        } else if (inTable && !trimmed.startsWith('|')) {
            closeTable();
        }

        // 5. Lists (Unordered and Ordered)
        const ulMatch = trimmed.match(/^^[\-\*]\s+(.*)$/);
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

        // 6. Blank lines
        if (trimmed === '') {
            continue;
        }

        // 7. Regular paragraph
        closeList();
        closeTable();
        htmlResult.push(`<p class="my-4 text-slate-750 dark:text-slate-300 leading-relaxed">${line}</p>`);
    }

    // Close any unclosed list/table at the end
    closeList();
    closeTable();

    let finalHtml = htmlResult.join('\n');

    // Inline formatting: Bold, Italic, Code
    finalHtml = finalHtml
        .replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-950 dark:text-white">$1</strong>')
        .replace(/\*(.*?)\*(?!\*)/g, '<em class="italic text-slate-600 dark:text-slate-400">$1</em>')
        .replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-900 text-pink-600 dark:text-pink-400 rounded text-xs font-mono">$1</code>');

    return finalHtml;
};
