export const TEAM_LAST_COMMENT_DISPLAY_NAMES = [
    'Jacques Tredoux',
    'Nico van Pletzen',
]

function normalizeAuthorLabel(s) {
    return String(s).trim().toLowerCase().replace(/\s+/g, ' ')
}

export function isLastCommentFromTeam(lastAuthor) {
    if (lastAuthor == null || !String(lastAuthor).trim()) return false
    const n = normalizeAuthorLabel(String(lastAuthor))
    return TEAM_LAST_COMMENT_DISPLAY_NAMES.some(
        (name) => normalizeAuthorLabel(name) === n,
    )
}

export function isTicketNeedsTeamFollowUp(lastCommentAuthor) {
    return !isLastCommentFromTeam(lastCommentAuthor ?? null)
}

export function getLastCommentAuthorLabel(ticket) {
    if (ticket?.last_comment_author) return ticket.last_comment_author
    const comments = ticket?.comments ?? []
    if (!comments.length) return null
    const last = comments[comments.length - 1]
    return last?.author ?? last?.author_name ?? null
}
