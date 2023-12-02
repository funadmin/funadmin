
    public function get{{$methodName}}Attr($value)
    {
        $value = $value ? $value :  '';
        return $valueArr = explode(',', $value);
    }