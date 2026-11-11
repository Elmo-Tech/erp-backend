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
        if (! $connection) return ['connected' => false, 'reason' => 'Google Drive is not connected.'];

        try {
            $response = $this->driveGet(self::DRIVE_API.'/about', ['fields' => 'user(emailAddress)']);
            $data = $this->jsonOrFail($response, 'Could not verify Google Drive connection.');
            $connection->refresh();
            return [
                'connected' => true,
                'expiresAt' => $connection->expires_at?->toIso8601String(),
                'accountEmail' => data_get($data, 'user.emailAddress'),
            ];
        } catch (ConnectionException $exception) {
            return ['connected' => false, 'reason' => 'Could not reach Google Drive. Please try again.'];
        } catch (RuntimeException $exception) {
            return ['connected' => false, 'reason' => $exception->getMessage()];
        }
    }

    public function folders(?string $search = null): array
    {
        $query = "mimeType = 'application/vnd.google-apps.folder' and trashed = false";
        if ($search) $query .= " and name contains '".$this->escapeQuery($search)."'";
        $files = $this->listDriveFiles($query, 'id,name,webViewLink');
        return collect($files)->map(fn (array $folder) => [
            'id' => $folder['id'], 'name' => $folder['name'], 'url' => $folder['webViewLink'] ?? null,
        ])->all();
    }

    public function files(string $folderId): array
    {
        $files = $this->listDriveFiles("'".$this->escapeQuery($folderId)."' in parents and trashed = false", 'id,name,mimeType,size,webViewLink,webContentLink,createdTime,modifiedTime');
        return collect($files)->map(fn (array $file) => $this->mapFile($file))->all();
    }

    private function listDriveFiles(string $query, string $fields): array
    {
        $files = [];
        $pageToken = null;
        do {
            $response = $this->driveGet(self::DRIVE_API.'/files', array_filter([
                'q' => $query, 'fields' => 'nextPageToken,files('.$fields.')', 'pageSize' => 100,
                'orderBy' => 'modifiedTime desc', 'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true', 'pageToken' => $pageToken,
            ], fn ($value) => $value !== null));
            $data = $this->jsonOrFail($response, 'Could not load Google Drive items.');
            $files = array_merge($files, $data['files'] ?? []);
            $pageToken = $data['nextPageToken'] ?? null;
        } while ($pageToken);
        return $files;
    }

    public function upload(string $folderId, UploadedFile $file): array
    {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $url = 'https://www.googleapis.com/upload/drive/v3/files?'.http_build_query([
            'uploadType' => 'resumable', 'supportsAllDrives' => 'true',
            'fields' => 'id,name,mimeType,size,webViewLink,webContentLink,createdTime,modifiedTime',
        ]);
        $start = fn (string $token) => Http::withToken($token)->withHeaders([
            'X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) $file->getSize(),
        ])->timeout(30)->post($url, ['name' => $file->getClientOriginalName(), 'parents' => [$folderId]]);
        $response = $start($this->accessToken());
        if ($response->status() === 401) $response = $start($this->accessToken(true));
        $this->jsonOrFail($response, 'Could not start the Google Drive upload.');
        $sessionUrl = $response->header('Location');
        $parts = is_string($sessionUrl) ? parse_url($sessionUrl) : false;
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'www.googleapis.com' || ! str_starts_with($parts['path'] ?? '', '/upload/drive/v3/files')) {
            throw new RuntimeException('Google Drive did not return a valid upload session.');
        }
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) throw new RuntimeException('Could not read the selected file.');
        try {
            $uploaded = Http::withToken($this->accessToken())->withHeaders(['Content-Length' => (string) $file->getSize()])
                ->withBody($stream, $mime)->timeout(600)->put($sessionUrl);
            return $this->mapFile($this->jsonOrFail($uploaded, 'Could not upload the file to Google Drive.'));
        } finally {
            if (is_resource($stream)) fclose($stream);
        }
    }

    private function driveGet(string $url, array $query): Response
    {
        $response = Http::withToken($this->accessToken())->timeout(30)->get($url, $query);
        if ($response->status() === 401) {
            $response = Http::withToken($this->accessToken(true))->timeout(30)->get($url, $query);
        }
        return $response;
    }

    private function accessToken(bool $forceRefresh = false): string
    {
        $connection = GoogleDriveConnection::query()->latest('id')->first();
        if (! $connection) throw new RuntimeException('Google Drive is not connected.');
        if (! $forceRefresh && $connection->access_token && (! $connection->expires_at || $connection->expires_at->gt(now()->addMinute()))) return $connection->access_token;
        if (! $connection->refresh_token) throw new RuntimeException('Google Drive access expired. Reconnect the Google account.');
        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_drive.client_id'), 'client_secret' => config('services.google_drive.client_secret'),
            'grant_type' => 'refresh_token', 'refresh_token' => $connection->refresh_token,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException($response->status() === 400 || $response->status() === 401
                ? 'Google Drive authorization expired. Reconnect the Google account.'
                : 'Google Drive could not refresh its connection. Please try again.');
        }
        $data = $response->json();
        if (empty($data['access_token'])) throw new RuntimeException('Google Drive returned an invalid access token. Reconnect the Google account.');
        $connection->update([
            'access_token' => $data['access_token'], 'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
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

        throw new RuntimeException(match ($response->status()) {
            401 => 'Google Drive authorization expired. Reconnect the Google account.',
            403 => 'Google Drive denied access. Check the connected account and folder permissions.',
            404 => 'The Google Drive folder or file is unavailable. Check that it still exists and is shared with the connected account.',
            429 => 'Google Drive is receiving too many requests. Please try again later.',
            default => $message,
        });
    }

    private function escapeQuery(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }
}
