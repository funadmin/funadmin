/**
    * @NodeAnnotation(title="List")
    */
    public function index()
    {
        {{$relationSearch}}
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames('','',{{$status}});
            $count = $this->modelClass
                ->where($where)
                ->{{$joinIndexMethod}}->count();
            $list = $this->modelClass
                ->where($where)
                ->{{$joinIndexMethod}}
                ->order($sort)
                ->page($this->page,$this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        return view();
    }

    /**
    * @NodeAnnotation(title="Recycle")
    */
    public function recycle()
    {
        {{$relationSearch}}
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames('','',false);
            $where[]  = ['{{$table}}status','=',-1];
            $count = $this->modelClass
                ->where($where)
                ->{{$joinIndexMethod}}->count();
            $list = $this->modelClass
                ->where($where)
                ->{{$joinIndexMethod}}
                ->order($sort)
                ->page($this->page,$this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        return view('index');
    }