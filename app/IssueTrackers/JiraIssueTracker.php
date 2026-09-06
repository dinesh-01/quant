<?php

namespace App\IssueTrackers;

use App\Models\IssueTracker as IssueTrackerModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Jira Cloud REST v3. Auth is email + API token over Basic.
 */
final class JiraIssueTracker implements IssueTracker
{
    public function __construct(private readonly IssueTrackerModel $tracker) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        try {
            $response = $this->client()->get($this->apiUrl('/myself'));
        } catch (ConnectionException) {
            return [
                'ok' => false,
                'message' => __('Could not reach the tracker.'),
            ];
        }

        if ($response->successful()) {
            return [
                'ok' => true,
                'message' => __('Reached the tracker (HTTP :status).', [
                    'status' => $response->status(),
                ]),
            ];
        }

        if ($response->unauthorized() || $response->forbidden()) {
            return [
                'ok' => false,
                'message' => __('The tracker rejected the credentials (HTTP :status).', [
                    'status' => $response->status(),
                ]),
            ];
        }

        return [
            'ok' => false,
            'message' => __('The tracker returned an error (:status).', [
                'status' => $response->status(),
            ]),
        ];
    }

    public function createIssue(string $summary, string $description): RemoteIssue
    {
        $projectKey = (string) $this->tracker->setting('project_key', '');
        $issueType = (string) $this->tracker->setting('issue_type', 'Bug');

        try {
            $response = $this->client()->post($this->apiUrl('/issue'), [
                'fields' => [
                    'project' => ['key' => $projectKey],
                    'issuetype' => ['name' => $issueType],
                    'summary' => Str::limit($summary, 255, ''),
                    'description' => $this->document($description),
                ],
            ]);
        } catch (ConnectionException) {
            throw new IssueTrackerException(__('Could not reach the tracker.'));
        }

        if (! $response->successful()) {
            throw new IssueTrackerException($this->errorMessage($response->json()) ?: __('The tracker rejected the issue.'));
        }

        $key = (string) $response->json('key');

        if ($key === '') {
            throw new IssueTrackerException(__('The tracker did not return an issue key.'));
        }

        return $this->fetchIssue($key) ?? new RemoteIssue(
            $key,
            $this->browseUrl($key),
            null,
            $summary,
        );
    }

    public function fetchIssue(string $issueId): ?RemoteIssue
    {
        try {
            $response = $this->client()->get($this->apiUrl('/issue/'.rawurlencode($issueId)), [
                'fields' => 'status,summary',
            ]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->notFound()) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $key = (string) ($response->json('key') ?: $issueId);

        return new RemoteIssue(
            $key,
            $this->browseUrl($key),
            $response->json('fields.status.name'),
            $response->json('fields.summary'),
        );
    }

    public function browseUrl(string $issueId): string
    {
        return rtrim($this->tracker->base_url, '/').'/browse/'.$issueId;
    }

    private function client(): PendingRequest
    {
        $client = Http::connectTimeout(3)
            ->timeout(10)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth(
                (string) $this->tracker->setting('email', ''),
                (string) $this->tracker->setting('api_token', ''),
            );

        $proxy = $this->tracker->setting('proxy');

        if (is_string($proxy) && $proxy !== '') {
            $client = $client->withOptions(['proxy' => $proxy]);
        }

        return $client;
    }

    private function apiUrl(string $path): string
    {
        return rtrim($this->tracker->base_url, '/').'/rest/api/3'.$path;
    }

    /**
     * @return array{type: string, version: int, content: list<array<string, mixed>>}
     */
    private function document(string $text): array
    {
        $paragraphs = preg_split("/\n{2,}/", trim($text)) ?: [];

        if ($paragraphs === [] || $paragraphs === ['']) {
            $paragraphs = [''];
        }

        return [
            'type' => 'doc',
            'version' => 1,
            'content' => array_map(fn (string $paragraph): array => [
                'type' => 'paragraph',
                'content' => $paragraph === ''
                    ? []
                    : [['type' => 'text', 'text' => $paragraph]],
            ], $paragraphs),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function errorMessage(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $messages = [];

        foreach ($payload['errorMessages'] ?? [] as $message) {
            if (is_string($message) && $message !== '') {
                $messages[] = $message;
            }
        }

        foreach ($payload['errors'] ?? [] as $message) {
            if (is_string($message) && $message !== '') {
                $messages[] = $message;
            }
        }

        return $messages === [] ? null : implode(' ', $messages);
    }
}
