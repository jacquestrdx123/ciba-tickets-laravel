export function formatCommentDate(dateStr) {
    if (!dateStr) return ''
    try {
        return new Date(dateStr).toLocaleString()
    } catch {
        return dateStr
    }
}

export function getCommentAuthorInitials(author) {
    if (!author) return '?'
    return author.split(' ').map((p) => p[0] ?? '').join('').toUpperCase().slice(0, 2)
}
