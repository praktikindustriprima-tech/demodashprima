export interface PrintColumn<T> {
    key: keyof T;
    label: string;
}

export interface PrintOptions {
    title?: string;
    subtitle?: string;
    styles?: string;
}

const DEFAULT_STYLES = `
    table { width: 100%; border-collapse: collapse; font-family: sans-serif; font-size: 14px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background-color: #f3f4f6; font-weight: 600; }
    h1 { font-family: sans-serif; margin-bottom: 8px; }
    p { font-family: sans-serif; color: #666; font-size: 13px; margin-bottom: 16px; }
`;

export function printToPdf<T extends Record<string, any>>(
    data: T[],
    columns: PrintColumn<T>[],
    options: PrintOptions = {},
) {
    if (data.length === 0) {
return;
}

    const { title = 'Print', subtitle, styles = DEFAULT_STYLES } = options;

    const escapeHtml = (val: string) => {
        return String(val ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };

    const thead = columns.map(c => '<th>' + escapeHtml(c.label) + '</th>').join('');
    const rows = data.map(item =>
        '<tr>' + columns.map(c => '<td>' + escapeHtml(String(item[c.key] ?? '')) + '</td>').join('') + '</tr>'
    ).join('');

    const subtitleHtml = subtitle ? '<p>' + escapeHtml(subtitle) + '</p>' : '';

    const printWindow = window.open('', '_blank');

    if (!printWindow) {
return;
}

    const html = '<html><head><title>' + escapeHtml(title) + '</title><style>' + styles + '</style></head><body>' +
        '<h1>' + escapeHtml(title) + '</h1>' + subtitleHtml +
        '<table><thead><tr>' + thead + '</tr></thead><tbody>' + rows + '</tbody></table>' +
        '</body></html>';

    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.print();
    printWindow.close();
}
