<?php

function decimalValue(mixed $value): string
{
    if (!is_string($value) && !is_numeric($value)) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid numeric value.'
        ], 422);
    }

    $value = trim((string) $value);

    if (!preg_match('/^\d+(\.\d{1,6})?$/', $value)) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid decimal value.'
        ], 422);
    }

    if (bccomp($value, '0', 6) <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'Value must be greater than zero.'
        ], 422);
    }

    return $value;
}
