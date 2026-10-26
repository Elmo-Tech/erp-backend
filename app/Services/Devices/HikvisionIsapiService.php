<?php

namespace App\Services\Devices;

use App\Models\Device;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Thin ISAPI (Hikvision) client.
 *
 * The DS-K1T8003 series speaks ISAPI over HTTP/HTTPS with digest
 * authentication, using the same credentials as the device web page.
 * Every request is therefore issued with `withDigestAuth()`.
 */
class HikvisionIsapiService
{
    /**
     * Perform a GET request against the device.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(Device $device, string $path, array $query = []): array
    {
        return $this->request($device, 'GET', $path, $query);
    }

    /**
     * Perform a POST request against the device.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(Device $device, string $path, array $payload = []): array
    {
        return $this->request($device, 'POST', $path, [], $payload);
    }

    /**
     * Perform a PUT request against the device.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function put(Device $device, string $path, array $payload = []): array
    {
        return $this->request($device, 'PUT', $path, [], $payload);
    }

    /**
     * Perform a DELETE request against the device.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function delete(Device $device, string $path, array $query = []): array
    {
        return $this->request($device, 'DELETE', $path, $query);
    }

    /**
     * Read the device information block.
     *
     * @return array<string, mixed>
     */
    public function deviceInfo(Device $device): array
    {
        return $this->get($device, '/ISAPI/System/deviceInfo');
    }

    /**
     * Test the connection and the credentials of a device.
     *
     * @return array<string, mixed>
     */
    public function testConnection(Device $device): array
    {
        $info = $this->deviceInfo($device);

        return [
            'reachable' => true,
            'deviceName' => $info['deviceName'] ?? null,
            'deviceId' => $info['deviceID'] ?? null,
            'model' => $info['model'] ?? null,
            'serialNumber' => $info['serialNumber'] ?? null,
            'firmware' => $info['firmwareVersion'] ?? null,
        ];
    }

    /**
     * Search the persons stored on the device.
     *
     * @return list<array<string, mixed>>
     */
    public function listUsers(Device $device, int $maxResults = 30): array
    {
        $users = [];
        $position = 0;
        $searchId = 'erp'.substr(md5((string) $device->id), 0, 12);

        // The device caps the page size; keep paging until it stops returning rows.
        do {
            $response = $this->post($device, '/ISAPI/AccessControl/UserInfo/Search', [
                'UserInfoSearchCond' => [
                    'searchID' => $searchId,
                    'searchResultPosition' => $position,
                    'maxResults' => $maxResults,
                ],
            ]);

            $list = $response['UserInfoList'] ?? [];
            $page = is_array($list) ? array_values(array_filter($list, 'is_array')) : [];

            $users = array_merge($users, $page);

            $position += $maxResults;
        } while (count($page) === $maxResults && $position < 10000);

        return $users;
    }

    /**
     * Create or update a person on the device.
     *
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    public function upsertUser(Device $device, array $user): array
    {
        return $this->put($device, '/ISAPI/AccessControl/UserInfo/SetUp', [
            'UserInfo' => $user,
        ]);
    }

    /**
     * Delete a person from the device.
     */
    public function deleteUser(Device $device, string $employeeNo): array
    {
        return $this->put($device, '/ISAPI/AccessControl/UserInfo/SetUp', [
            'UserInfo' => [
                'employeeNo' => $employeeNo,
                'deleteUser' => true,
            ],
        ]);
    }

    /**
     * Read the fingerprint templates enrolled for a person.
     *
     * @return list<array<string, mixed>>
     */
    public function listFingerprints(Device $device, string $employeeNo): array
    {
        $response = $this->post($device, '/ISAPI/AccessControl/FingerPrintUpload', [
            'FingerPrintCond' => [
                'searchID' => 'erp'.substr(md5((string) $device->id), 0, 12),
                'employeeNo' => $employeeNo,
            ],
        ]);

        $list = $response['FingerPrintList'] ?? [];

        if (! is_array($list)) {
            return [];
        }

        return array_values(array_filter($list, 'is_array'));
    }

    /**
     * Download a fingerprint template onto the device.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function setFingerprint(Device $device, array $payload): array
    {
        return $this->post($device, '/ISAPI/AccessControl/FingerPrint/SetUp', [
            'FingerPrintCfg' => $payload,
        ]);
    }

    /**
     * Search the access control event log.
     *
     * @param  array<string, mixed>  $condition
     * @return list<array<string, mixed>>
     */
    public function searchAccessEvents(Device $device, array $condition = []): array
    {
        $searchId = $condition['searchID'] ?? 'erp'.substr(md5((string) $device->id), 0, 12);

        $response = $this->post($device, '/ISAPI/AccessControl/AcsEvent', [
            'AcsEventCond' => array_merge([
                'searchID' => $searchId,
                'searchResultPosition' => 0,
                'maxResults' => 30,
                'major' => 0,
                'minor' => 0,
            ], $condition, ['searchID' => $searchId]),
        ]);

        $list = $response['AcsEvent'] ?? $response['InfoList'] ?? [];

        if (! is_array($list)) {
            return [];
        }

        return array_values(array_filter($list, 'is_array'));
    }

    /**
     * Configure the device to push events to the given webhook URL.
     *
     * @return array<string, mixed>
     */
    public function configureWebhook(Device $device, string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        return $this->put($device, '/ISAPI/Event/notification/httpHosts/1', [
            'HttpHostNotification' => [
                'id' => '1',
                'url' => $url,
                'protocolType' => 'HTTP',
                'parameterFormatType' => 'JSON',
                'addressingFormatType' => 'ipaddress',
                'ipAddress' => $host,
                'portNo' => $port ?: 80,
                'httpAuthenticationMethod' => 'none',
            ],
        ]);
    }

    /**
     * Remove the push notification configuration from the device.
     *
     * @return array<string, mixed>
     */
    public function clearWebhook(Device $device): array
    {
        return $this->delete($device, '/ISAPI/Event/notification/httpHosts/1');
    }

    /**
     * Issue the actual HTTP request.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(
        Device $device,
        string $method,
        string $path,
        array $query = [],
        array $payload = []
    ): array {
        $url = $device->baseUrl().$path;

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        try {
            $request = Http::withDigestAuth((string) $device->username, (string) $device->password)
                ->timeout((int) config('devices.isapi.timeout', 15))
                ->connectTimeout((int) config('devices.isapi.connect_timeout', 5))
                ->withOptions(['verify' => (bool) $device->verify_tls])
                ->acceptJson()
                ->asJson();

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url),
                'POST' => $request->post($url, $payload),
                'PUT' => $request->put($url, $payload),
                'DELETE' => $request->delete($url),
                default => throw new RuntimeException("Unsupported HTTP method: {$method}"),
            };
        } catch (ConnectionException $exception) {
            Log::warning('ISAPI connection failed', [
                'device_id' => $device->id,
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException(__('messages.device_unreachable'), 0, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        return $this->handleResponse($device, $response, $method, $url);
    }

    /**
     * Convert an ISAPI response into an array, raising on failures.
     *
     * @return array<string, mixed>
     */
    private function handleResponse(Device $device, Response $response, string $method, string $url): array
    {
        if ($response->successful()) {
            $json = $response->json();

            return is_array($json) ? $json : [];
        }

        $status = $response->status();

        if ($status === 401 || $status === 403) {
            Log::warning('ISAPI authentication failed', [
                'device_id' => $device->id,
                'url' => $url,
            ]);

            throw new RuntimeException(__('messages.device_auth_failed'));
        }

        if ($status === 404) {
            throw new RuntimeException(__('messages.device_endpoint_unsupported'));
        }

        Log::warning('ISAPI request failed', [
            'device_id' => $device->id,
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'body' => substr($response->body(), 0, 500),
        ]);

        throw new RuntimeException(__('messages.device_request_failed', ['status' => $status]));
    }
}
