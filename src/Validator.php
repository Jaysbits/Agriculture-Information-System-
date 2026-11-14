<?php
declare(strict_types=1);

/**
 * Small server-side validation helper.
 *
 *   $v = new Validator($_POST);
 *   $v->required('first_name', 'First name')->max('first_name', 100, 'First name');
 *   if (!$v->passes()) { $errors = $v->errors(); }
 */
final class Validator
{
    private array $data;
    /** @var array<string,string> */
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function value(string $field): string
    {
        $value = $this->data[$field] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    public function has(string $field): bool
    {
        return $this->value($field) !== '';
    }

    public function addError(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;
        return $this;
    }

    public function required(string $field, string $label): self
    {
        if ($this->value($field) === '') {
            $this->addError($field, $label . ' is required.');
        }
        return $this;
    }

    public function max(string $field, int $length, string $label): self
    {
        if (mb_strlen($this->value($field)) > $length) {
            $this->addError($field, sprintf('%s must not exceed %d characters.', $label, $length));
        }
        return $this;
    }

    public function min(string $field, int $length, string $label): self
    {
        $value = $this->value($field);
        if ($value !== '' && mb_strlen($value) < $length) {
            $this->addError($field, sprintf('%s must be at least %d characters.', $label, $length));
        }
        return $this;
    }

    public function numeric(string $field, string $label, float $min = -INF, float $max = INF, bool $allowEmpty = false): self
    {
        $value = $this->value($field);
        if ($value === '') {
            if (!$allowEmpty) {
                $this->addError($field, $label . ' is required.');
            }
            return $this;
        }
        if (!is_numeric($value)) {
            $this->addError($field, $label . ' must be a valid number.');
            return $this;
        }
        $number = (float) $value;
        if ($number < $min || $number > $max) {
            if (is_finite($min) && is_finite($max)) {
                $this->addError($field, sprintf('%s must be between %s and %s.', $label, $this->pretty($min), $this->pretty($max)));
            } elseif (is_finite($min)) {
                $this->addError($field, sprintf('%s must be at least %s.', $label, $this->pretty($min)));
            } else {
                $this->addError($field, sprintf('%s must not be more than %s.', $label, $this->pretty($max)));
            }
        }
        return $this;
    }

    public function integer(string $field, string $label, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX, bool $allowEmpty = false): self
    {
        $value = $this->value($field);
        if ($value === '') {
            if (!$allowEmpty) {
                $this->addError($field, $label . ' is required.');
            }
            return $this;
        }
        if (!preg_match('/^-?\d+$/', $value)) {
            $this->addError($field, $label . ' must be a whole number.');
            return $this;
        }
        $number = (int) $value;
        if ($number < $min || $number > $max) {
            $this->addError($field, sprintf('%s must be between %d and %d.', $label, $min, $max));
        }
        return $this;
    }

    /** Accepts YYYY-MM-DD only. */
    public function date(string $field, string $label, bool $required = true): self
    {
        $value = $this->value($field);
        if ($value === '') {
            if ($required) {
                $this->addError($field, $label . ' is required.');
            }
            return $this;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            $this->addError($field, $label . ' must be a valid date.');
            return $this;
        }
        if ((int) $dt->format('Y') < 2000) {
            $this->addError($field, $label . ' must be on or after January 1, 2000.');
        }
        return $this;
    }

    public function dateNotAfter(string $field, string $otherField, string $label, string $otherLabel): self
    {
        $a = $this->value($field);
        $b = $this->value($otherField);
        if ($a !== '' && $b !== '' && strtotime($a) !== false && strtotime($b) !== false && $a < $b) {
            $this->addError($field, sprintf('%s cannot be earlier than %s.', $label, $otherLabel));
        }
        return $this;
    }

    public function dateNotBefore(string $field, string $otherField, string $label, string $otherLabel): self
    {
        $a = $this->value($field);
        $b = $this->value($otherField);
        if ($a !== '' && $b !== '' && strtotime($a) !== false && strtotime($b) !== false && $a > $b) {
            $this->addError($field, sprintf('%s cannot be later than %s.', $label, $otherLabel));
        }
        return $this;
    }

    public function notFuture(string $field, string $label): self
    {
        $value = $this->value($field);
        if ($value !== '' && strtotime($value) !== false && $value > date('Y-m-d')) {
            $this->addError($field, $label . ' cannot be in the future.');
        }
        return $this;
    }

    /** @param array<int,string> $allowed */
    public function inList(string $field, array $allowed, string $label): self
    {
        $value = $this->value($field);
        if ($value === '') {
            $this->addError($field, $label . ' is required.');
            return $this;
        }
        if (!in_array($value, array_map('strval', $allowed), true)) {
            $this->addError($field, 'Please choose a valid ' . mb_strtolower($label) . ' from the list.');
        }
        return $this;
    }

    public function regex(string $field, string $pattern, string $label, string $hint): self
    {
        $value = $this->value($field);
        if ($value !== '' && !preg_match($pattern, $value)) {
            $this->addError($field, sprintf('%s %s', $label, $hint));
        }
        return $this;
    }

    /** Ensure the value is an existing primary key in $table. */
    public function exists(string $field, string $table, string $column, string $label): self
    {
        $value = $this->value($field);
        if ($value === '') {
            $this->addError($field, $label . ' is required.');
            return $this;
        }
        if (!preg_match('/^\d+$/', $value)) {
            $this->addError($field, 'Please choose a valid ' . mb_strtolower($label) . ' from the list.');
            return $this;
        }
        $found = Database::count(
            "SELECT COUNT(*) FROM {$table} WHERE {$column} = :v",
            [':v' => (int) $value]
        );
        if ($found === 0) {
            $this->addError($field, 'The selected ' . mb_strtolower($label) . ' does not exist.');
        }
        return $this;
    }

    /** Ensure the value is unique in $table (case-insensitive), optionally ignoring one row. */
    public function unique(
        string $field,
        string $table,
        string $column,
        string $label,
        ?int $ignoreId = null,
        string $idColumn = 'id',
        ?string $extraCondition = null,
        array $extraParams = []
    ): self {
        $value = $this->value($field);
        if ($value === '') {
            return $this;
        }
        $sql = "SELECT COUNT(*) FROM {$table}
                 WHERE lower({$column}) = lower(:v)";
        $params = [':v' => $value];
        if ($ignoreId !== null) {
            $sql .= " AND {$idColumn} <> :ignore";
            $params[':ignore'] = $ignoreId;
        }
        if ($extraCondition !== null) {
            $sql .= ' AND ' . $extraCondition;
            $params = array_merge($params, $extraParams);
        }
        if (Database::count($sql, $params) > 0) {
            $this->addError($field, $label . ' is already used by another record.');
        }
        return $this;
    }

    public function custom(string $condition, string $field, string $message): self
    {
        if ($condition) {
            $this->addError($field, $message);
        }
        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Trimmed values for the given field names; empty strings become null. */
    public function validated(array $fields): array
    {
        $clean = [];
        foreach ($fields as $field) {
            $value = $this->value($field);
            $clean[$field] = $value === '' ? null : $value;
        }
        return $clean;
    }

    private function pretty(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ','), '0'), '.');
    }
}
