<?php

namespace App\Services\Ai;

use App\Ai\Contracts\AiResponse;

class AiResponseSchemaValidator
{
    public function errors(AiResponse $response, array $schema): array
    {
        if (! $response->success) return ['Provider marked the response as unsuccessful.'];
        if ($schema === []) return [];

        $data = $response->toArray();
        $errors = [];
        foreach ((array) ($schema['required'] ?? []) as $field) {
            if (! array_key_exists($field, $data) && ! array_key_exists($field, $response->data)) $errors[] = "Missing required field {$field}.";
        }
        foreach ((array) ($schema['properties'] ?? []) as $field => $rules) {
            $exists = array_key_exists($field, $data) || array_key_exists($field, $response->data);
            if (! $exists) continue;
            $value = array_key_exists($field, $data) ? $data[$field] : $response->data[$field];
            $expected = $rules['type'] ?? null;
            if ($expected && ! $this->matches($value, $expected)) $errors[] = "Field {$field} must be {$expected}.";
        }
        return $errors;
    }

    private function matches(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value), 'number' => is_int($value) || is_float($value), 'integer' => is_int($value),
            'boolean' => is_bool($value), 'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ! array_is_list($value), 'null' => $value === null,
            default => true,
        };
    }
}
