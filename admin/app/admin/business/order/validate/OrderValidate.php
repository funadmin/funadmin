<?php

declare(strict_types=1);

namespace app\admin\business\order\validate;

use Webman\Validation\Validator;

final class OrderValidate
{
    private array $rules = array (
  'field_1' => 
  array (
    0 => 'string',
    1 => 'nullable',
  ),
  'field_2' => 
  array (
    0 => 'string',
    1 => 'nullable',
  ),
  'field_3' => 
  array (
    0 => 'string',
    1 => 'nullable',
  ),
  'field_5' => 
  array (
    0 => 'numeric',
    1 => 'nullable',
  ),
  'field_5_copy' => 
  array (
    0 => 'numeric',
    1 => 'nullable',
  ),
  'field_4' => 
  array (
    0 => 'string',
    1 => 'nullable',
  ),
);
    private string $error = '';
    private int|string|null $updateId = null;

    public function forUpdate(int|string $id, array $data = []): self
    {
        $this->updateId = $id;
        foreach (array (
  0 => 'field_4',
) as $field) {
            if (!array_key_exists($field, $data)) unset($this->rules[$field]);
        }
        return $this;
    }

    public function check(array $data): bool
    {
        $rules = $this->rules;
        foreach ($rules as &$fieldRules) {
            foreach ($fieldRules as &$rule) $rule = str_replace('{id}', $this->updateId === null ? 'NULL' : (string) $this->updateId, $rule);
            unset($rule);
        }
        unset($fieldRules);
        if ($rules === []) {
            $this->error = '';
            return true;
        }
        $validator = Validator::make($data, $rules);
        $valid = $validator->passes();
        $this->error = $valid ? '' : (string) $validator->errors()->first();
        return $valid;
    }

    public function getError(): string { return $this->error; }
}

