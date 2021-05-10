/**
    * @NodeAnotation(title="List")
    */
    public function index()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
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