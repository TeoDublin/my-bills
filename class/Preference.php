<?php

class Preference
{

    public function all_for_user(int $user_id): array
    {

        $user_id = (int) $user_id;
        $rows = SQL()->select("SELECT scope, preference_key, preference_value FROM user_preferences WHERE user_id = {$user_id}");

        $preferences = [];

        foreach ($rows as $row) {

            $scope = $row['scope'];
            $key = $row['preference_key'];
            $preferences[$scope][$key] = $this->decode_value($row['preference_value']);
        }

        return $preferences;
    }

    public function get(int $user_id, string $key, string $scope = 'global', mixed $default = null): mixed
    {

        $sql = SQL();
        $scope_escaped = $sql->escape($scope);
        $key_escaped = $sql->escape($key);
        $user_id = (int) $user_id;

        $rows = $sql->select("
            SELECT preference_value
            FROM user_preferences
            WHERE user_id = {$user_id}

              AND scope = '{$scope_escaped}'
              AND preference_key = '{$key_escaped}'
            LIMIT 1
        ");

        if (!$rows) {

            return $default;
        }

        return $this->decode_value($rows[0]['preference_value']);
    }

    public function set(int $user_id, string $key, mixed $value, string $scope = 'global'): void
    {

        $sql = SQL();
        $user_id = (int) $user_id;
        $scope_escaped = $sql->escape($scope);
        $key_escaped = $sql->escape($key);
        $encoded_value = $sql->escape($this->encode_value($value));

        $sql->query("
            INSERT INTO user_preferences (user_id, scope, preference_key, preference_value, created_at, updated_at)
            VALUES ({$user_id}, '{$scope_escaped}', '{$key_escaped}', '{$encoded_value}', NOW(), NOW())

            ON DUPLICATE KEY UPDATE
                preference_value = VALUES(preference_value),
                updated_at = NOW()
        ");
    }

    public function theme_for_user(int $user_id, string $default = 'light'): string
    {

        $theme = $this->get($user_id, 'theme', 'global', $default);
        return in_array($theme, ['light', 'dark'], true) ? $theme : $default;

    }

    private function encode_value(mixed $value): string
    {

        if (is_array($value) || is_object($value)) {

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {

            return $value ? '1' : '0';
        }

        return strval($value);

    }

    private function decode_value(string $value): mixed
    {

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {

            return $decoded;
        }

        return $value;

    }

}
