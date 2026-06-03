<?php

namespace App\Modules\Agent\Services;

use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AgentCommandParserService
{
    /**
     * @return array<string, mixed>
     */
    public function parse(User $user, string $command): array
    {
        $apiKey = config('services.openai.key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            abort(503, 'OpenAI API key is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-4.1-mini'),
                'instructions' => $this->instructions(),
                'input' => $this->input($user, $command),
                'temperature' => 0.1,
                'max_output_tokens' => 900,
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'agent_action_parse',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI parser request failed: '.$response->body());
        }

        return $this->decodeStructuredOutput($response->json());
    }

    private function instructions(): string
    {
        return implode("\n", [
            'You convert a user command into exactly one Business OS agent action.',
            'Supported actions: project.create_tasks, project.start_timer, reports.get.',
            'Never execute actions. Return only structured JSON matching the schema.',
            'Use only IDs present in the provided context. Do not invent projectId, taskId, userId, or clientId.',
            'If required IDs or intent are ambiguous, set clarificationNeeded=true, action="unknown", payload={}, and ask one concise clarification question.',
            'For project.create_tasks, create concise task titles and optional deliverables from the command.',
            'For reports.get, map report names to overview, projects, team-time, or sales-finance.',
            'Dates must be ISO YYYY-MM-DD when present. If a relative date is unclear, ask for clarification.',
            'Arabic and English commands are both valid.',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function input(User $user, string $command): array
    {
        return [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => json_encode([
                            'command' => $command,
                            'context' => $this->context($user),
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(User $user): array
    {
        $projects = Project::query()
            ->with(['executionTasks:id,project_id,title,status,assignee_id'])
            ->latest()
            ->limit(80)
            ->get()
            ->filter(fn (Project $project): bool => Gate::forUser($user)->allows('view', $project))
            ->take(50)
            ->map(fn (Project $project): array => [
                'projectId' => $project->id,
                'title' => $project->title,
                'status' => $project->status?->value ?? (string) $project->status,
                'tasks' => $project->executionTasks
                    ->take(30)
                    ->map(fn ($task): array => [
                        'taskId' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status?->value ?? (string) $task->status,
                        'assigneeId' => $task->assignee_id,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $users = User::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (User $internalUser): array => [
                'userId' => $internalUser->id,
                'name' => $internalUser->name,
            ])
            ->values()
            ->all();

        return [
            'currentUser' => [
                'userId' => $user->id,
                'name' => $user->name,
            ],
            'projects' => $projects,
            'users' => $users,
            'reports' => ['overview', 'projects', 'team-time', 'sales-finance'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'payload', 'confidence', 'clarificationNeeded', 'clarificationQuestion'],
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['project.create_tasks', 'project.start_timer', 'reports.get', 'unknown'],
                ],
                'payload' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
                'clarificationNeeded' => [
                    'type' => 'boolean',
                ],
                'clarificationQuestion' => [
                    'type' => ['string', 'null'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function decodeStructuredOutput(array $response): array
    {
        $text = $response['output_text'] ?? null;

        if (! is_string($text)) {
            foreach (($response['output'] ?? []) as $item) {
                foreach (($item['content'] ?? []) as $content) {
                    if (isset($content['text']) && is_string($content['text'])) {
                        $text = $content['text'];
                        break 2;
                    }
                }
            }
        }

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('OpenAI parser response did not include structured text.');
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI parser returned invalid JSON.');
        }

        return $decoded;
    }
}
