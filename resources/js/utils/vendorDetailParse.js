export function parseVendorDetail(raw) {
    if (!raw) return null
    return {
        id: raw.id,
        ticket_number: raw.ticket_number ?? raw.ticketNumber,
        subject: raw.subject,
        status: raw.status,
        client_name: raw.client_name ?? raw.clientName,
        created_at: raw.created_at ?? raw.createdAt,
        last_comment_at: raw.last_comment_at ?? raw.lastCommentAt,
        comments: raw.comments ?? [],
        attachments: raw.attachments ?? [],
        description: raw.description ?? raw.body ?? '',
    }
}
