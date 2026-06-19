export function branchesFromTicket(ticket) {
    return ticket?.github_branches ?? []
}

export function githubBranchListForDisplay(ticket) {
    const branches = branchesFromTicket(ticket)
    return branches.map((b) => ({
        name: b.name,
        is_default: b.is_default ?? false,
        merged: true,
        openPr: false,
    }))
}

export function isPrimaryGithubBranch(primary, branchName) {
    return primary === branchName
}

export function githubBranchBadgeColor(branch, primary) {
    if (isPrimaryGithubBranch(primary, branch.name)) return 'emerald'
    if (branch.openPr && !branch.merged) return 'red'
    if (!branch.merged) return 'amber'
    return 'gray'
}

export function githubBranchBadgeTitle(branch, primary) {
    if (isPrimaryGithubBranch(primary, branch.name)) {
        return `${branch.name} (primary branch)`
    }
    if (branch.openPr && !branch.merged) return `${branch.name} (open PR, not merged)`
    if (!branch.merged) return `${branch.name} (not merged into default)`
    return branch.name
}
