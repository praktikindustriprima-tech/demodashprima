import ExcelJS from 'exceljs';

export interface ExportColumn<T> {
    key: keyof T;
    label: string;
    type?: 'string' | 'number' | 'date';
}

export interface ExportOptions {
    filename?: string;
    sheetName?: string;
}

const HEADER_FILL: ExcelJS.Fill = {
    type: 'pattern',
    pattern: 'solid',
    fgColor: { argb: 'FF4472C4' },
};

const HEADER_FONT: Partial<ExcelJS.Font> = {
    bold: true,
    color: { argb: 'FFFFFFFF' },
    size: 11,
};

const BORDER: Partial<ExcelJS.Borders> = {
    top: { style: 'thin' },
    left: { style: 'thin' },
    bottom: { style: 'thin' },
    right: { style: 'thin' },
};

export async function exportToExcel<T extends Record<string, any>>(
    data: T[],
    columns: ExportColumn<T>[],
    options: ExportOptions = {},
) {
    if (!data.length || !columns.length) {
        return;
    }

    const {
        filename = `export_${new Date().toISOString().slice(0, 10)}.xlsx`,
        sheetName = 'Sheet1',
    } = options;

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet(sheetName);

    sheet.columns = columns.map(c => ({
        header: c.label,
        key: String(c.key),
        width: Math.max(c.label.length + 4, 14),
    }));

    const headerRow = sheet.getRow(1);
    headerRow.eachCell(cell => {
        cell.fill = HEADER_FILL;
        cell.font = HEADER_FONT;
        cell.border = BORDER;
        cell.alignment = { vertical: 'middle', horizontal: 'center' };
    });
    headerRow.height = 22;

    data.forEach(item => {
        const row = sheet.addRow(
            columns.map(c => {
                const val = item[c.key];

                if (val == null) {
                    return '';
                }

                if (c.type === 'number') {
                    const num = Number(val);

                    return isNaN(num) ? val : num;
                }

                if (c.type === 'date') {
                    const d = new Date(val);

                    return isNaN(d.getTime()) ? val : d;
                }

                return String(val);
            }),
        );
        row.eachCell(cell => {
            cell.border = BORDER;
            cell.alignment = { vertical: 'middle' };
        });
    });

    const safeFilename = filename.replace(/\.csv$/i, '') + '.xlsx';

    await workbook.xlsx.writeBuffer().then(buffer => {
        const blob = new Blob([buffer], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = safeFilename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    });
}
