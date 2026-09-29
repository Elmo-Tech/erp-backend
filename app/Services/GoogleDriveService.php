<?php

namespace App\Services;

use App\Models\GoogleDriveConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveService
{
    private const DRIVE_API = 'https://www.googleapis.com/drive/v3';

    public function oauthUrl(?string $state = null): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(array_filter([
            'client_id' => config('services.google_drive.client_id'),
            'redirect_uri' => config('services.google_drive.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]));
    }

    public function storeAuthorizationCode(string $code): GoogleDriveConnection
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'redirect_uri' => config('services.google_drive.redirect_uri'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        $data = $this->jsonOrFail($response, 'Google Drive authorization failed');

        return GoogleDriveConnection::query()->updateOrCreate(
            ['id' => 1],
            [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? GoogleDriveConnection::query()->find(1)?->refresh_token,
                'expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
            ]
        );
    }

    public function status(): array
    {
        $connection = GoogleDriveConnection::query()->latest('id')->first();

        if (! $connection) {
            return [
                'connected' => false,
                'reason' => 'Google Drive is not connected.',
            ];
        }

        $status = [
            'connected' => false,
        ];

        if ($connection->expires_at) {
            $status['expiresAt'] = $connection->expires_at->toIso8601String();
        }

        if (! $connection->access_token) {
            $status['reason'] = 'Google Drive connection is invalid. Reconnect Google Drive.';

            return $status;
        }

        // Status checks never refresh or persist tokens.
        if ($connection->expires_at?->lte(now())) {
            $status['reason'] = 'Google Drive access has expired. Reconnect Google Drive.';

            return $status;
        }

        try {
            $response = Http::withToken($connection->access_token)->get(self::DRIVE_API.'/about', [
                'fields' => 'user(emailAddress)',
            ]);
        } catch (ConnectionException) {
            $status['reason'] = 'Google Drive connection could not be verified. Try again or reconnect Google Drive.';

            return $status;
        }

        if ($response->successful()) {
            $status['connected'] = true;

            $email = data_get($response->json(), 'user.emailAddress');

            if (is_string($email) && $email !== '') {
                $status['accountEmail'] = $email;
            }

            return $status;
        }

        $status['reason'] = match ($response->status()) {
            401 => 'Google Drive authorization is no longer valid. Reconnect Google Drive.',
            403 => 'Google Drive authorization can no longer access Drive. Reconnect Google Drive.',
            default => 'Google Drive connection could not be verified. Try again or reconnect Google Drive.',
        };

        return $status;
    }

    public function folders(?string $search = null): array
    {
        $query = "mimeType = 'application/vnd.google-apps.folder' and trashed = false";

        if ($search) {
            $query .= " and name contains '".$this->escapeQuery($search)."'";
        }

        $response = Http::withToken($this->accessToken())->get(self::DRIVE_API.'/files', [
            'q' => $query,
            'fields' => 'files(id,name,webViewLink)',
            'pageSize' => 20,
            'orderBy' => 'modifiedTime desc',
        ]);

        return collect($this->jsonOrFail($response, 'Could not load Google Drive folders')['files'] ?? [])
            ->map(fn (array $folder) => [
                'id' => $folder['id'],
                'name' => $folder['name'],
                'url' => $folder['webViewLink'] ?? null,
            ])
            ->all();
    }

    public function files(string $folderId): array
    {
        $response = Http::withToken($this->accessToken())->get(self::DRIVE_API.'/files', [
            'q' => "'".$this->escapeQuery($folderId)."' in parents and trashed = false",
            'fields' => 'files(id,name,mimeType,size,webViewLink,webContentLink,createdTime,modifiedTime)',
            'pageSize' => 100,
            'orderBy' => 'modifiedTime desc',
        ]);

        return collect($this->jsonOrFail($response, 'Could not load Google Drive files')['files'] ?? [])
            ->map(fn (array $file) => $this->mapFile($file))
            ->all();
    }

    public function upload(string $folderId, UploadedFile $file): array
    {
        $response = Http::withToken($this->accessToken())
            ->attach('metadata', json_encode([
                'name' => $file->getClientOriginalName(),
                'parents' => [$folderId],
            ]), 'metadata.json', ['Content-Type' => 'application/json; charset=UTF-8'])
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName(), [
                'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
            ])
            ->post('https://www.googleapis.com/upload/drive/v3/files?'.http_build_query([
                'uploadType' => 'multipart',
                'fields' => 'id,name,mimeType,size,webViewLink,webContentLink,createdTime,modifiedTime',
            ]));

        return $this->mapFile($this->jsonOrFail($response, 'Could not upload file to Google Drive'));
    }

    private function accessToken(): string
    {
        $connection = GoogleDriveConnection::query()->latest('id')->first();

        if (! $connection) {
            throw new RuntimeException('Google Drive is not connected');
        }

        if (! $connection->expires_at || $connection->expires_at->gt(now()->addMinute())) {
            return $connection->access_token;
        }

        if (! $connection->refresh_token) {
            throw new RuntimeException('Google Drive refresh token is missing');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
        ]);

        $data = $this->jsonOrFail($response, 'Could not refresh Google Drive token');

        $connection->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
            'expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
        ]);

        return $connection->access_token;
    }

    private function mapFile(array $file): array
    {
        return [
            'id' => $file['id'],
            'name' => $file['name'],
            'mimeType' => $file['mimeType'] ?? null,
            'size' => isset($file['size']) ? (int) $file['size'] : null,
            'webViewLink' => $file['webViewLink'] ?? null,
            'webContentLink' => $file['webContentLink'] ?? null,
            'createdTime' => $file['createdTime'] ?? null,
            'modifiedTime' => $file['modifiedTime'] ?? null,
        ];
    }

    private function jsonOrFail(Response $response, string $message): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw new RuntimeException($message);
    }

    private function escapeQuery(string $value): string
    {
        return str_replace("'", "\\'", $value);
    }
}
