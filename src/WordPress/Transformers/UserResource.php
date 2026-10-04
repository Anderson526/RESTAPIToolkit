<?php

namespace RestApiToolkit\WordPress\Transformers;

/**
 * Transforma WP_User a salida segura. Nunca expone passwords, hashes,
 * cookies ni application password secrets.
 */
class UserResource
{
    public static function make(\WP_User $user, bool $withEmail = false): array
    {
        $data = [
            'id'           => $user->ID,
            'username'     => $user->user_login,
            'display_name' => $user->display_name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'roles'        => array_values($user->roles),
            'registered'   => mysql2date('c', $user->user_registered, false),
            'avatar'       => get_avatar_url($user->ID),
        ];

        if ($withEmail) {
            $data['email'] = $user->user_email;
        }

        return apply_filters('rat_user_response', $data, $user);
    }
}
