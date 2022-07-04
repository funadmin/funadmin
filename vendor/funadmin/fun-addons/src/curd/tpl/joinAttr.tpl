
    public function {{$method}}()
    {
        return {{$joinModel}}::column("{{$values}}", 'id');
    }