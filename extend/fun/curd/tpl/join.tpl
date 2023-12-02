public function {{$joinName}}()
    {
        return $this->{{$joinMethod}}('{{$joinModel}}','{{$joinForeignKey}}','{{$joinPrimaryKey}}');
    }
