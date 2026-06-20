export interface ExportColumn<T> {
    key: keyof T;
    label: string;
}

export interface ExportOptions {
    filename?: string;
    delimiter?: string;
    addBom?: boolean;
}

const escapeCsv = (val: string, delimiter: string) => {
    const str = String(val ?? '');

    return `"${str.replace(/"/g, '""')}"`;
};

export function exportToExcel<T extends Record<string, any>>(
    data: T[],
    columns: ExportColumn<T>[],
    options: ExportOptions = {},
) {
    if (data.length === 0) {
return;
}

    const {
        filename = `export_${new Date().toISOString().slice(0, 10)}.csv`,
        delimiter = ';',
        addBom = true,
    } = options;

    const headerRow = columns.map(c => escapeCsv(c.label, delimiter)).join(delimiter);
    const dataRows = data.map(item =>
        columns.map(c => escapeCsv(String(item[c.key] ?? ''), delimiter)).join(delimiter)
    );

    // Add BOM for Excel UTF-8 recognition
    const bom = addBom ? '﻿' : '';
    const csvContent = bom + [headerRow, ...dataRows].join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = filename.endsWith('.csv') ? filename : `${filename}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    window.URL.revokeObjectURL(url);
}
