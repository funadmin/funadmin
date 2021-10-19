public function {{$joinName}}()
    {
        return $this->{{$joinMethod}}('{{$joinModel}}', '{{$joinPrimaryKey}}','{{$joinForeignKey}}');
    }
