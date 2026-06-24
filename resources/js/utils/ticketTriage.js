import { isTicketNeedsTeamFollowUp } from './teamLastComment'

export function isTicketInAttentionQueue(ticket) {
    if (ticket?._triage?.isAwaitingClient) return false
    return isTicketNeedsTeamFollowUp(ticket?.last_comment_author)
}

export function isTicketAwaitingClient(ticket) {
    return !!ticket?._triage?.isAwaitingClient
}

export function shouldShowInParkedQueue(record, ticket) {
    if (!record) return false
    if (!ticket) return true
    return isTicketNeedsTeamFollowUp(ticket.last_comment_author)
}

export function hasNewCommentsSinceParked(row) {
    const current = row?.last_comment_at
    if (current == null || !String(current).trim()) return false
    const baseline = row?.parked_last_comment_at ?? row?.awaiting_client_at
    if (baseline == null || !String(baseline).trim()) return false
    const currentTime = Date.parse(String(current))
    const baselineTime = Date.parse(String(baseline))
    if (!Number.isFinite(currentTime) || !Number.isFinite(baselineTime)) return false
    return currentTime > baselineTime
}

export function matchesTicketSearch(ticket, query) {
    if (!query) return true
    const q = query.trim().toLowerCase()
    return (
        String(ticket?.ticket_number ?? '').toLowerCase().includes(q) ||
        String(ticket?.subject ?? ticket?.summary ?? '').toLowerCase().includes(q) ||
        (ticket?.last_comment_author &&
            ticket.last_comment_author.toLowerCase().includes(q))
    )
}

export function withTriage(ticket, awaitingClientById, idKey = 'vendor_id', priorityById = null) {
    const triageRecord = awaitingClientById?.get(ticket[idKey])
    const priorityRecord = priorityById?.get(ticket[idKey])
    return {
        ...ticket,
        _triage: {
            isAwaitingClient: !!triageRecord,
            isPriority: !!priorityRecord,
            record: triageRecord ?? null,
            priorityRecord: priorityRecord ?? null,
        },
    }
}

export function parkedRowFromRecord(record, ticket) {
    return {
        ...ticket,
        vendor_id: record.ticket_id,
        ticket_number: record.ticket_number ?? ticket?.ticket_number,
        parked_last_comment_at: record.parked_last_comment_at,
        awaiting_client_at: record.awaiting_client_at,
        awaiting_client_note: record.awaiting_client_note,
        _triage: {
            isAwaitingClient: true,
            record,
        },
    }
}

export function priorityRowFromRecord(record, ticket) {
    return {
        ...ticket,
        vendor_id: record.ticket_id,
        ticket_number: record.ticket_number ?? ticket?.ticket_number,
        prioritized_at: record.prioritized_at,
        priority_note: record.priority_note,
        _triage: {
            ...(ticket?._triage ?? { isAwaitingClient: false, record: null }),
            isPriority: true,
            priorityRecord: record,
        },
    }
}


