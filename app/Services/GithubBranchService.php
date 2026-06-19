<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Http;

class GithubBranchService
{
    /** @return array{synced: int, synced_at: \Illuminate\Support\Carbon} */
    public function sync(): array
    {
        $now = now();
        $defaultBranch = config('app.github_default_branch', 'master');
        $allBranches = $this->fetchAllBranches();
        $updated = 0;

        Ticket::query()
            ->select(['id', 'ticket_number'])
            ->orderBy('id')
            ->chunkById(100, function ($tickets) use ($allBranches, $defaultBranch, &$updated) {
                foreach ($tickets as $ticket) {
                    $ticket->update([
                        'github_branches' => $this->matchBranches($ticket->ticket_number, $allBranches, $defaultBranch),
                    ]);
                    $updated++;
                }
            });

        return [
            'synced'    => $updated,
            'synced_at' => $now,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function matchBranches(string $ticketNumber, array $allBranches, ?string $defaultBranch = null): array
    {
        $ticketNumber = trim($ticketNumber);
        if ($ticketNumber === '') {
            return [];
        }

        $defaultBranch ??= config('app.github_default_branch', 'master');
        $ticketPattern = preg_quote($ticketNumber, '/');
        $matched = [];

        foreach ($allBranches as $branch) {
            $name = $branch['name'] ?? null;
            if (!$name || !preg_match("/{$ticketPattern}/i", $name)) {
                continue;
            }

            $matched[] = [
                'name'       => $name,
                'is_default' => $name === $defaultBranch,
                'sha'        => $branch['commit']['sha'] ?? null,
            ];
        }

        return $matched;
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchAllBranches(): array
    {
        $owner = config('app.github_owner');
        $repo = config('app.github_repo');
        $branches = [];
        $page = 1;

        do {
            $batch = $this->githubFetch("/repos/{$owner}/{$repo}/branches?per_page=100&page={$page}");
            if (!is_array($batch) || $batch === []) {
                break;
            }

            $branches = array_merge($branches, $batch);
            $page++;
        } while (count($batch) === 100);

        return $branches;
    }

    private function githubFetch(string $path): mixed
    {
        $token = config('app.github_token');
        $owner = config('app.github_owner');
        $repo = config('app.github_repo');

        if (!$token || !$owner || !$repo) {
            throw new \RuntimeException('Missing GitHub config (GITHUB_TOKEN, GITHUB_OWNER, GITHUB_REPO in .env)');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ])->get("https://api.github.com{$path}");

        if ($response->failed()) {
            $body = $response->json() ?? [];
            $message = $body['message'] ?? $response->body();
            throw new \RuntimeException(is_string($message) ? $message : 'GitHub API error');
        }

        return $response->json();
    }
}
