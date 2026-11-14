<?php

namespace App\Update;

/**
 * Prepares updater state for the admin UI: step list + secret redaction.
 */
class UpdateStatePresenter
{
    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function present(array $state): array
    {
        $presented = $this->redact($state);
        $presented['steps_ui'] = $this->stepsUi($state);

        return $presented;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<array{id: string, label: string, status: string}>
     */
    public function stepsUi(array $state): array
    {
        $records = is_array($state['steps'] ?? null) ? $state['steps'] : [];
        $out = [];
        foreach (UpdateStep::sequence() as $step) {
            $record = is_array($records[$step->value] ?? null) ? $records[$step->value] : [];
            $out[] = [
                'id' => $step->value,
                'label' => $step->label(),
                'status' => (string) ($record['status'] ?? UpdateStateMachine::STEP_PENDING),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function redact(array $data): array
    {
        $secretValues = $this->secretValues();

        return $this->walk($data, $secretValues);
    }

    /**
     * @return list<string>
     */
    private function secretValues(): array
    {
        $values = [
            (string) config('app.key'),
            (string) env('APP_KEY', ''),
            (string) env('DB_PASSWORD', ''),
            (string) config('database.connections.mysql.password', ''),
        ];

        return array_values(array_filter($values, fn (string $v) => $v !== ''));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $secretValues
     * @return array<string, mixed>
     */
    private function walk(array $data, array $secretValues): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $keyString = is_string($key) ? $key : (string) $key;
            if ($this->isSensitiveKey($keyString)) {
                $out[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->walk($value, $secretValues);

                continue;
            }

            if (is_string($value)) {
                $out[$key] = $this->redactString($value, $secretValues);

                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        return in_array($normalized, [
            'app_key',
            'db_password',
            'password',
            'password_confirmation',
            'secret',
            'maintenance_secret',
            'recovery_token',
            'token',
            'authorization',
        ], true);
    }

    /**
     * @param  list<string>  $secretValues
     */
    private function redactString(string $value, array $secretValues): string
    {
        foreach ($secretValues as $secret) {
            if ($secret !== '' && str_contains($value, $secret)) {
                $value = str_replace($secret, '[redacted]', $value);
            }
        }

        return $value;
    }
}
