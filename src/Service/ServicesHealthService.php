<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Http\Client;
use Throwable;

/**
 * Santé des services Python (OR-Tools / Prophet) via GET /health.
 */
class ServicesHealthService
{
    private const TIMEOUT_SECONDS = 1;

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => self::TIMEOUT_SECONDS]);
    }

    /**
     * @return list<array{key: string, label: string, ok: bool, detail: string|null}>
     */
    public function check(): array
    {
        return [
            $this->probe(
                'solver',
                'Solver',
                (string)Configure::read('PythonSolver.url', 'http://127.0.0.1:8000'),
            ),
            $this->probe(
                'prophet',
                'Prophet',
                (string)Configure::read('PythonForecast.url', 'http://127.0.0.1:8001'),
            ),
        ];
    }

    /**
     * @return array{key: string, label: string, ok: bool, detail: string|null}
     */
    private function probe(string $key, string $label, string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/health';

        try {
            $response = $this->http->get($url);
            $code = $response->getStatusCode();
            if ($code < 200 || $code >= 300) {
                return [
                    'key' => $key,
                    'label' => $label,
                    'ok' => false,
                    'detail' => 'HTTP ' . $code,
                ];
            }

            $body = $response->getJson();
            if (is_array($body) && array_key_exists('status', $body) && $body['status'] !== 'ok') {
                return [
                    'key' => $key,
                    'label' => $label,
                    'ok' => false,
                    'detail' => 'status=' . (string)$body['status'],
                ];
            }

            return [
                'key' => $key,
                'label' => $label,
                'ok' => true,
                'detail' => null,
            ];
        } catch (Throwable $e) {
            $msg = trim($e->getMessage());
            if (strlen($msg) > 80) {
                $msg = substr($msg, 0, 77) . '...';
            }

            return [
                'key' => $key,
                'label' => $label,
                'ok' => false,
                'detail' => $msg !== '' ? $msg : 'injoignable',
            ];
        }
    }
}
