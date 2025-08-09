<?php

namespace Mcp\SchemaShowcaseExample;

use DateTime;
use DateInterval;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class SchemaShowcaseElements
{
    /**
     * Validates and formats text with string constraints.
     * Demonstrates: minLength, maxLength, pattern validation.
     */
    #[McpTool(
        name: 'format_text',
        description: 'Formats text with validation constraints. Text must be 5-100 characters and contain only letters, numbers, spaces, and basic punctuation.'
    )]
    public function formatText(
        #[Schema(
            type: 'string',
            description: 'The text to format',
            minLength: 5,
            maxLength: 100,
            pattern: '^[a-zA-Z0-9\s\.,!?\-]+$'
        )]
        string $text,

        #[Schema(
            type: 'string',
            description: 'Format style',
            enum: ['uppercase', 'lowercase', 'title', 'sentence']
        )]
        string $format = 'sentence'
    ): array {
        fwrite(STDERR, "Format text tool called: text='$text', format='$format'\n");

        $formatted = match ($format) {
            'uppercase' => strtoupper($text),
            'lowercase' => strtolower($text),
            'title' => ucwords(strtolower($text)),
            'sentence' => ucfirst(strtolower($text)),
            default => $text
        };

        return [
            'original' => $text,
            'formatted' => $formatted,
            'length' => strlen($text),
            'format_applied' => $format
        ];
    }

    /**
     * Performs mathematical operations with numeric constraints.
     * 
     * Demonstrates: METHOD-LEVEL Schema
     */
    #[McpTool(name: 'calculate_range')]
    #[Schema(
        type: 'object',
        properties: [
            'first' => [
                'type' => 'number',
                'description' => 'First number (must be between 0 and 1000)',
                'minimum' => 0,
                'maximum' => 1000
            ],
            'second' => [
                'type' => 'number',
                'description' => 'Second number (must be between 0 and 1000)',
                'minimum' => 0,
                'maximum' => 1000
            ],
            'operation' => [
                'type' => 'string',
                'description' => 'Operation to perform',
                'enum' => ['add', 'subtract', 'multiply', 'divide', 'power']
            ],
            'precision' => [
                'type' => 'integer',
                'description' => 'Decimal precision (must be multiple of 2, between 0-10)',
                'minimum' => 0,
                'maximum' => 10,
                'multipleOf' => 2
            ]
        ],
        required: ['first', 'second', 'operation'],
    )]
    public function calculateRange(float $first, float $second, string $operation, int $precision = 2): array
    {
        fwrite(STDERR, "Calculate range tool called: $first $operation $second (precision: $precision)\n");

        $result = match ($operation) {
            'add' => $first + $second,
            'subtract' => $first - $second,
            'multiply' => $first * $second,
            'divide' => $second != 0 ? $first / $second : null,
            'power' => pow($first, $second),
            default => null
        };

        if ($result === null) {
            return [
                'error' => $operation === 'divide' ? 'Division by zero' : 'Invalid operation',
                'inputs' => compact('first', 'second', 'operation', 'precision')
            ];
        }

        return [
            'result' => round($result, $precision),
            'operation' => "$first $operation $second",
            'precision' => $precision,
            'within_bounds' => $result >= 0 && $result <= 1000000
        ];
    }

    /**
     * Processes user profile data with object schema validation.
     * Demonstrates: object properties, required fields, additionalProperties.
     */
    #[McpTool(
        name: 'validate_profile',
        description: 'Validates and processes user profile data with strict schema requirements.'
    )]
    public function validateProfile(
        #[Schema(
            type: 'object',
            description: 'User profile information',
            properties: [
                'name' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'maxLength' => 50,
                    'description' => 'Full name'
                ],
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                    'description' => 'Valid email address'
                ],
                'age' => [
                    'type' => 'integer',
                    'minimum' => 13,
                    'maximum' => 120,
                    'description' => 'Age in years'
                ],
                'role' => [
                    'type' => 'string',
                    'enum' => ['user', 'admin', 'moderator', 'guest'],
                    'description' => 'User role'
                ],
                'preferences' => [
                    'type' => 'object',
                    'properties' => [
                        'notifications' => ['type' => 'boolean'],
                        'theme' => ['type' => 'string', 'enum' => ['light', 'dark', 'auto']]
                    ],
                    'additionalProperties' => false
                ]
            ],
            required: ['name', 'email', 'age'],
            additionalProperties: true
        )]
        array $profile
    ): array {
        fwrite(STDERR, "Validate profile tool called with: " . json_encode($profile) . "\n");

        $errors = [];
        $warnings = [];

        // Additional business logic validation
        if (isset($profile['age']) && $profile['age'] < 18 && ($profile['role'] ?? 'user') === 'admin') {
            $errors[] = 'Admin role requires age 18 or older';
        }

        if (isset($profile['email']) && !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (!isset($profile['role'])) {
            $warnings[] = 'No role specified, defaulting to "user"';
            $profile['role'] = 'user';
        }

        return [
            'valid' => empty($errors),
            'profile' => $profile,
            'errors' => $errors,
            'warnings' => $warnings,
            'processed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Manages a list of items with array constraints.
     * Demonstrates: array items, minItems, maxItems, uniqueItems.
     */
    #[McpTool(
        name: 'manage_list',
        description: 'Manages a list of items with size and uniqueness constraints.'
    )]
    public function manageList(
        #[Schema(
            type: 'array',
            description: 'List of items to manage (2-10 unique strings)',
            items: [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 30
            ],
            minItems: 2,
            maxItems: 10,
            uniqueItems: true
        )]
        array $items,

        #[Schema(
            type: 'string',
            description: 'Action to perform on the list',
            enum: ['sort', 'reverse', 'shuffle', 'deduplicate', 'filter_short', 'filter_long']
        )]
        string $action = 'sort'
    ): array {
        fwrite(STDERR, "Manage list tool called with " . count($items) . " items, action: $action\n");

        $original = $items;
        $processed = $items;

        switch ($action) {
            case 'sort':
                sort($processed);
                break;
            case 'reverse':
                $processed = array_reverse($processed);
                break;
            case 'shuffle':
                shuffle($processed);
                break;
            case 'deduplicate':
                $processed = array_unique($processed);
                break;
            case 'filter_short':
                $processed = array_filter($processed, fn($item) => strlen($item) <= 10);
                break;
            case 'filter_long':
                $processed = array_filter($processed, fn($item) => strlen($item) > 10);
                break;
        }

        return [
            'original_count' => count($original),
            'processed_count' => count($processed),
            'action' => $action,
            'original' => $original,
            'processed' => array_values($processed), // Re-index array
            'stats' => [
                'average_length' => count($processed) > 0 ? round(array_sum(array_map('strlen', $processed)) / count($processed), 2) : 0,
                'shortest' => count($processed) > 0 ? min(array_map('strlen', $processed)) : 0,
                'longest' => count($processed) > 0 ? max(array_map('strlen', $processed)) : 0,
            ]
        ];
    }

    /**
     * Generates configuration with format validation.
     * Demonstrates: format constraints (date-time, uri, etc).
     */
    #[McpTool(
        name: 'generate_config',
        description: 'Generates configuration with format-validated inputs.'
    )]
    public function generateConfig(
        #[Schema(
            type: 'string',
            description: 'Application name (alphanumeric with hyphens)',
            pattern: '^[a-zA-Z0-9\-]+$',
            minLength: 3,
            maxLength: 20
        )]
        string $appName,

        #[Schema(
            type: 'string',
            description: 'Valid URL for the application',
            format: 'uri'
        )]
        string $baseUrl,

        #[Schema(
            type: 'string',
            description: 'Environment type',
            enum: ['development', 'staging', 'production']
        )]
        string $environment = 'development',

        #[Schema(
            type: 'boolean',
            description: 'Enable debug mode'
        )]
        bool $debug = true,

        #[Schema(
            type: 'integer',
            description: 'Port number (1024-65535)',
            minimum: 1024,
            maximum: 65535
        )]
        int $port = 8080
    ): array {
        fwrite(STDERR, "Generate config tool called for app: $appName\n");

        $config = [
            'app' => [
                'name' => $appName,
                'env' => $environment,
                'debug' => $debug,
                'url' => $baseUrl,
                'port' => $port,
            ],
            'generated_at' => date('c'), // ISO 8601 format
            'version' => '1.0.0',
            'features' => [
                'logging' => $environment !== 'production' || $debug,
                'caching' => $environment === 'production',
                'analytics' => $environment === 'production',
                'rate_limiting' => $environment !== 'development',
            ]
        ];

        return [
            'success' => true,
            'config' => $config,
            'validation' => [
                'app_name_valid' => preg_match('/^[a-zA-Z0-9\-]+$/', $appName) === 1,
                'url_valid' => filter_var($baseUrl, FILTER_VALIDATE_URL) !== false,
                'port_in_range' => $port >= 1024 && $port <= 65535,
            ]
        ];
    }

    /**
     * Processes time-based data with date-time format validation.
     * Demonstrates: date-time format, exclusiveMinimum, exclusiveMaximum.
     */
    #[McpTool(
        name: 'schedule_event',
        description: 'Schedules an event with time validation and constraints.'
    )]
    public function scheduleEvent(
        #[Schema(
            type: 'string',
            description: 'Event title (3-50 characters)',
            minLength: 3,
            maxLength: 50
        )]
        string $title,

        #[Schema(
            type: 'string',
            description: 'Event start time in ISO 8601 format',
            format: 'date-time'
        )]
        string $startTime,

        #[Schema(
            type: 'number',
            description: 'Duration in hours (minimum 0.5, maximum 24)',
            minimum: 0.5,
            maximum: 24,
            multipleOf: 0.5
        )]
        float $durationHours,

        #[Schema(
            type: 'string',
            description: 'Event priority level',
            enum: ['low', 'medium', 'high', 'urgent']
        )]
        string $priority = 'medium',

        #[Schema(
            type: 'array',
            description: 'List of attendee email addresses',
            items: [
                'type' => 'string',
                'format' => 'email'
            ],
            maxItems: 20
        )]
        array $attendees = []
    ): array {
        fwrite(STDERR, "Schedule event tool called: $title at $startTime\n");

        $start = DateTime::createFromFormat(DateTime::ISO8601, $startTime);
        if (!$start) {
            $start = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $startTime);
        }

        if (!$start) {
            return [
                'success' => false,
                'error' => 'Invalid date-time format. Use ISO 8601 format.',
                'example' => '2024-01-15T14:30:00Z'
            ];
        }

        $end = clone $start;
        $end->add(new DateInterval('PT' . ($durationHours * 60) . 'M'));

        $event = [
            'id' => uniqid('event_'),
            'title' => $title,
            'start_time' => $start->format('c'),
            'end_time' => $end->format('c'),
            'duration_hours' => $durationHours,
            'priority' => $priority,
            'attendees' => $attendees,
            'created_at' => date('c')
        ];

        return [
            'success' => true,
            'event' => $event,
            'info' => [
                'attendee_count' => count($attendees),
                'is_all_day' => $durationHours >= 24,
                'is_future' => $start > new DateTime(),
                'timezone_note' => 'Times are in UTC'
            ]
        ];
    }
}
