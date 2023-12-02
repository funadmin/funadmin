
    public function get{{$methodName}}Attr($value)
    {
        $value = $value ? $value  : '';
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }