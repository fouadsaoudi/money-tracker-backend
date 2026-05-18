<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class PostmanCollectionEnhancer
{
    private const TOKEN_VARIABLE = 'authToken';

    /**
     * @throws RuntimeException
     */
    public function enhance(string $path): void
    {
        if (! File::exists($path)) {
            throw new RuntimeException("Postman collection not found at [{$path}].");
        }

        /** @var array<string, mixed> $collection */
        $collection = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $collection['variable'] = $this->upsertVariable($collection['variable'] ?? []);
        $collection['auth'] = [
            'type' => 'bearer',
            'bearer' => [
                [
                    'key' => 'token',
                    'type' => 'string',
                    'value' => '{{'.self::TOKEN_VARIABLE.'}}',
                ],
            ],
        ];
        $collection['event'] = $this->upsertCollectionEvent($collection['event'] ?? []);
        $collection['item'] = $this->enhanceItems($collection['item'] ?? []);

        File::put(
            $path,
            json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL
        );
    }

    /**
     * @param array<int, array<string, mixed>> $variables
     * @return array<int, array<string, mixed>>
     */
    private function upsertVariable(array $variables): array
    {
        foreach ($variables as &$variable) {
            if (($variable['key'] ?? null) === self::TOKEN_VARIABLE) {
                $variable['value'] = '';
                $variable['type'] = 'string';
                $variable['name'] = 'string';

                return $variables;
            }
        }

        $variables[] = [
            'id' => self::TOKEN_VARIABLE,
            'key' => self::TOKEN_VARIABLE,
            'value' => '',
            'type' => 'string',
            'name' => 'string',
        ];

        return $variables;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    private function upsertCollectionEvent(array $events): array
    {
        $scriptLines = [
            "pm.collectionVariables.set('baseUrl', pm.collectionVariables.get('baseUrl') || '{{baseUrl}}');",
        ];

        foreach ($events as &$event) {
            if (($event['listen'] ?? null) === 'prerequest') {
                $event['script'] = [
                    'type' => 'text/javascript',
                    'exec' => $scriptLines,
                ];

                return $events;
            }
        }

        $events[] = [
            'listen' => 'prerequest',
            'script' => [
                'type' => 'text/javascript',
                'exec' => $scriptLines,
            ],
        ];

        return $events;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function enhanceItems(array $items): array
    {
        foreach ($items as &$item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $item['item'] = $this->enhanceItems($item['item']);
                continue;
            }

            $name = (string) ($item['name'] ?? '');

            if (in_array($name, ['POST api/login', 'POST api/register'], true)) {
                $item['event'] = $this->tokenCaptureEvents();
            }
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tokenCaptureEvents(): array
    {
        return [
            [
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        'const data = pm.response.json();',
                        "if (data.token) {",
                        "    pm.collectionVariables.set('".self::TOKEN_VARIABLE."', data.token);",
                        "}",
                        "if (data.token_type) {",
                        "    pm.collectionVariables.set('authTokenType', data.token_type);",
                        '}',
                    ],
                ],
            ],
        ];
    }
}
