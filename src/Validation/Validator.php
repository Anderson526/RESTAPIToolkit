<?php

namespace RestApiToolkit\Validation;

use RestApiToolkit\REST\Exceptions\ValidationException;

/**
 * Validador declarativo por schema. Devuelve SOLO los campos declarados
 * (protección contra mass assignment) ya validados y sanitizados.
 *
 * Reglas soportadas: required, nullable, type (string, integer, float, boolean,
 * array, email, url), enum, min, max, min_length, max_length, regex, default,
 * sanitize ('text'|'html'|'key'|'none'), items_type, custom (callable).
 */
class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, array> $schema
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validate(array $data, array $schema): array
    {
        $errors = [];
        $output = [];

        foreach ($schema as $field => $rules) {
            $exists = array_key_exists($field, $data);
            $value  = $exists ? $data[$field] : null;
            $empty  = $value === null || $value === '';

            if ($empty) {
                if (!empty($rules['required'])) {
                    $errors[$field][] = __('El campo es obligatorio.', 'rest-api-toolkit');
                    continue;
                }
                if (array_key_exists('default', $rules)) {
                    $output[$field] = $rules['default'];
                } elseif ($exists && !empty($rules['nullable'])) {
                    $output[$field] = null;
                }
                continue;
            }

            $fieldErrors = [];
            $value = $this->applyType($value, $rules, $fieldErrors);

            if (!$fieldErrors) {
                $this->applyConstraints($value, $rules, $fieldErrors);
            }

            if (!$fieldErrors && isset($rules['custom']) && is_callable($rules['custom'])) {
                $result = call_user_func($rules['custom'], $value, $data);
                if ($result !== true) {
                    $fieldErrors[] = is_string($result) ? $result : __('Valor inválido.', 'rest-api-toolkit');
                }
            }

            if ($fieldErrors) {
                $errors[$field] = $fieldErrors;
                continue;
            }

            $output[$field] = $value;
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        return $output;
    }

    /** Coerción y validación de tipo. Sanitiza strings según la regla sanitize. */
    private function applyType($value, array $rules, array &$errors)
    {
        $type = $rules['type'] ?? 'string';

        switch ($type) {
            case 'integer':
                if (!is_numeric($value) || (string) (int) $value !== (string) preg_replace('/^\+/', '', trim((string) $value))) {
                    if (!is_int($value) && !ctype_digit(ltrim((string) $value, '-'))) {
                        $errors[] = __('Debe ser un número entero.', 'rest-api-toolkit');
                        return $value;
                    }
                }
                return (int) $value;

            case 'float':
            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = __('Debe ser un número.', 'rest-api-toolkit');
                    return $value;
                }
                return (float) $value;

            case 'boolean':
                if (is_bool($value)) {
                    return $value;
                }
                $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($filtered === null) {
                    $errors[] = __('Debe ser un booleano.', 'rest-api-toolkit');
                    return $value;
                }
                return $filtered;

            case 'array':
                if (!is_array($value)) {
                    $errors[] = __('Debe ser un array.', 'rest-api-toolkit');
                    return $value;
                }
                if (($rules['items_type'] ?? '') === 'integer') {
                    return array_map('intval', $value);
                }
                return map_deep($value, 'sanitize_text_field');

            case 'email':
                $value = sanitize_email((string) $value);
                if (!is_email($value)) {
                    $errors[] = __('Debe ser un email válido.', 'rest-api-toolkit');
                }
                return $value;

            case 'url':
                $value = esc_url_raw((string) $value);
                if ($value === '' || !wp_http_validate_url($value)) {
                    $errors[] = __('Debe ser una URL válida.', 'rest-api-toolkit');
                }
                return $value;

            case 'string':
            default:
                if (!is_scalar($value)) {
                    $errors[] = __('Debe ser una cadena de texto.', 'rest-api-toolkit');
                    return $value;
                }
                return $this->sanitizeString((string) $value, $rules['sanitize'] ?? 'text');
        }
    }

    private function sanitizeString(string $value, string $mode): string
    {
        switch ($mode) {
            case 'html':
                return wp_kses_post($value);
            case 'key':
                return sanitize_key($value);
            case 'none':
                return $value;
            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }

    private function applyConstraints($value, array $rules, array &$errors): void
    {
        if (isset($rules['enum']) && !in_array($value, $rules['enum'], true)) {
            $errors[] = sprintf(
                /* translators: %s: valores permitidos */
                __('Valor no permitido. Permitidos: %s', 'rest-api-toolkit'),
                implode(', ', array_map('strval', $rules['enum']))
            );
        }

        if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
            $errors[] = sprintf(__('El valor mínimo es %s.', 'rest-api-toolkit'), $rules['min']);
        }

        if (isset($rules['max']) && is_numeric($value) && $value > $rules['max']) {
            $errors[] = sprintf(__('El valor máximo es %s.', 'rest-api-toolkit'), $rules['max']);
        }

        if (is_string($value)) {
            $length = mb_strlen($value);
            if (isset($rules['min_length']) && $length < $rules['min_length']) {
                $errors[] = sprintf(__('Longitud mínima: %d caracteres.', 'rest-api-toolkit'), $rules['min_length']);
            }
            if (isset($rules['max_length']) && $length > $rules['max_length']) {
                $errors[] = sprintf(__('Longitud máxima: %d caracteres.', 'rest-api-toolkit'), $rules['max_length']);
            }
            if (isset($rules['regex']) && !preg_match($rules['regex'], $value)) {
                $errors[] = __('El formato no es válido.', 'rest-api-toolkit');
            }
        }
    }
}
