export const CRUD_FIELD_COMPONENTS = [
  { value: 'input', label: '单行输入' }, { value: 'password', label: '密码输入' },
  { value: 'textarea', label: '多行文本' }, { value: 'inputNumber', label: '数字输入' },
  { value: 'select', label: '下拉选择' }, { value: 'radio', label: '单选框' },
  { value: 'checkbox', label: '复选框' }, { value: 'switch', label: '开关' },
  { value: 'datetime', label: '日期时间' }, { value: 'date', label: '日期' },
  { value: 'time', label: '时间' }, { value: 'image', label: '单图上传' },
  { value: 'images', label: '多图上传' }, { value: 'file', label: '单文件上传' },
  { value: 'files', label: '多文件上传' }, { value: 'dictionary', label: '字典选择' },
  { value: 'json', label: 'JSON 编辑' }
] as const;

export const CRUD_VALIDATION_RULES = [
  { value: 'require', label: '必填 require' }, { value: 'integer', label: '整数 integer' },
  { value: 'number', label: '数字 number' }, { value: 'float', label: '浮点数 float' },
  { value: 'boolean', label: '布尔值 boolean' }, { value: 'email', label: '邮箱 email' },
  { value: 'url', label: '网址 url' }, { value: 'date', label: '日期 date' },
  { value: 'alpha', label: '纯字母 alpha' }, { value: 'alphaNum', label: '字母数字 alphaNum' },
  { value: 'max:', label: '最大值/长度 max:' }, { value: 'min:', label: '最小值/长度 min:' },
  { value: 'length:', label: '固定长度 length:' }, { value: 'regex:', label: '正则 regex:' }
] as const;

export const CRUD_SEARCH_OPERATORS = ['like', 'eq', 'in', 'range', 'gte', 'lte'] as const;
