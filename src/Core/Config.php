<?php

namespace RestApiToolkit\Core;

/**
 * Acceso centralizado a la configuración del plugin (opción rat_settings).
 */
class Config
{
    public const OPTION = 'rat_settings';

    /** @var array<string, mixed> */
    private static $defaults = [
        'logging_enabled'       => true,
        'log_retention_days'    => 30,
        'rate_limit_per_minute' => 60,
        'cors_origins'          => '',
        'show_donation_notice'  => true,
    ];

    public static function get(string $key, $fallback = null)
    {
        $settings = get_option(self::OPTION, []);
        if (is_array($settings) && array_key_exists($key, $settings) && $settings[$key] !== '') {
            return $settings[$key];
        }

        return array_key_exists($key, self::$defaults) ? self::$defaults[$key] : $fallback;
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return self::$defaults;
    }
}
