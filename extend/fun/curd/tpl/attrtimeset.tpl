
    public function set{{$methodName}}Attr($value)
    {
        $value = $value ? $value  : '';
        return $value == '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }