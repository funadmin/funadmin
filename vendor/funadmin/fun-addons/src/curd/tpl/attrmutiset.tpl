
    public function set{{$methodName}}Attr($value)
    {
        $value = $value ? $value : '';
        return is_array($value) ? implode(',', $value) : $value;
    }