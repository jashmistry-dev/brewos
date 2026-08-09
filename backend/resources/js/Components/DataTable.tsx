import React, { ReactNode } from 'react';
import EmptyState from './EmptyState';
import LoadingSpinner from './LoadingSpinner';

export interface Column<T> {
    header: string;
    accessorKey?: keyof T;
    cell?: (row: T) => ReactNode;
    className?: string;
}

export interface DataTableProps<T> {
    columns: Column<T>[];
    data: T[];
    isLoading?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
    keyExtractor: (row: T, index: number) => string | number;
}

export function DataTable<T>({
    columns,
    data,
    isLoading = false,
    emptyTitle = 'No data available',
    emptyDescription = 'There are no records to display at this time.',
    keyExtractor,
}: DataTableProps<T>) {
    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-16 bg-white border border-stone-200 rounded-xl shadow-sm">
                <LoadingSpinner size="lg" />
            </div>
        );
    }

    if (!data || data.length === 0) {
        return (
            <div className="bg-white border border-stone-200 rounded-xl p-8 shadow-sm">
                <EmptyState title={emptyTitle} description={emptyDescription} />
            </div>
        );
    }

    return (
        <div className="w-full overflow-x-auto bg-white border border-stone-200 rounded-xl shadow-sm">
            <table className="w-full text-left text-sm text-stone-600 border-collapse">
                <thead className="bg-stone-50 text-xs uppercase font-semibold text-stone-500 border-b border-stone-200">
                    <tr>
                        {columns.map((col, idx) => (
                            <th key={idx} scope="col" className={`px-6 py-3.5 ${col.className || ''}`}>
                                {col.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-stone-200">
                    {data.map((row, rowIdx) => (
                        <tr key={keyExtractor(row, rowIdx)} className="hover:bg-stone-50/80 transition-colors">
                            {columns.map((col, colIdx) => (
                                <td key={colIdx} className={`px-6 py-4 whitespace-nowrap ${col.className || ''}`}>
                                    {col.cell
                                        ? col.cell(row)
                                        : col.accessorKey
                                        ? String(row[col.accessorKey] ?? '')
                                        : null}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default DataTable;
