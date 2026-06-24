function escapeCsv(val) {
    const s = String(val ?? '')
    if (s.includes(',') || s.includes('"') || s.includes('\n')) {
        return `"${s.replace(/"/g, '""')}"`
    }
    return s
}

export function ticketsToCsv(tickets) {
    const headers = ['ID', 'Ticket #', 'Subject', 'Category', 'Client', 'Status', 'Closed on customer side', 'Last Comment At']
    const rows = tickets.map((t) => [
        t.id ?? '',
        t.ticket_number ?? '',
        t.subject ?? '',
        t.category?.name ?? '',
        t.client_name ?? '',
        t.status ?? '',
        t.closed_on_customer_side ? (t.closed_on_customer_side_at ?? 'Yes') : 'No',
        t.last_comment_at ?? '',
    ])
    return [headers, ...rows].map((row) => row.map(escapeCsv).join(',')).join('\n')
}

export function downloadCsv(content, filename = 'tickets.csv') {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
}
