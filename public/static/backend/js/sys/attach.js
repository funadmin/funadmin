define(['jquery','table','form','upload'], function (undefined,Table,Form,Upload) {

    var Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tablId: 'list',
                requests:{
                    index_url: 'sys.attach/index',
                    add_url: 'sys.attach/add',
                    edit_url: 'sys.attach/edit',
                    del_url: 'sys.attach/delete',
                    modify_url: 'sys.attach/modify',

                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tablId,
                url: Speed.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: 'ID', width: 80, fixed: true, sort: true},
                    {field: 'name', title:__('Name'), minWidth: 120,sort: true},
                    {field: 'original_name', title:__('OriginalName'), minWidth: 180,sort: true},
                    {field: 'mime', title: __('FileTye'), width: 120,sort: true ,templet:function (d) {
                            html = '';
                            if (d.mime == 'image/jpeg' || d.mime == 'image/gif' || d.mime == 'image/png' || d.mime == 'image/webp' || d.mime == 'image/bmp') {
                                html += '<img src="'+STATIC+'/backend/images/filetype/image.jpg" alt="'+__('Image')+'" width="50">'
                            } else if (d.mime == 'application/pdf') {
                                html += '<img src="'+STATIC+'/backend/images/filetype/pdf.jpg" alt="'+__('Pdf')+'" width="50">'
                            } else if (d.mime == 'application/zip') {
                                html += '<img src="'+STATIC+'/backend/images/filetype/zip.jpg" alt="'+__('Zip')+'" width="50">'
                            } else if (d.mime == 'application/msexcel' || d.mime == 'application/mspowerpoint' || d.mime == 'application/msword') {
                                html += '<img src="'+STATIC+'/backend/images/filetype/office.jpg" alt="'+__('Office')+'" width="50">'
                            } else {
                                html += '<img src="'+STATIC+'/backend/images/filetype/file.jpg" alt="'+__('File')+'" width="50">'
                            }
                            return html;

                        }
                    },
                    {field: 'path', title: __('Path'), width: 80, sort: true, templet:Table.templet.image},
                    {field: 'ext', title: __('Ext'), width: 120,sort: true},
                    {field: 'size', title: __('Size(K)'), width: 80, sort: true},
                    {field: 'driver', title: __('Driver'), width: 80,sort: true },
                    {field: 'status', title: __('Status'), width: 180, templet:Table.templet.switch,sort: true,
                        search: 'select',selectList: {0: __('Disabled'), 1: __('Enabled')},
                    },
                    {field: 'create_time', title: __('CreateTime'), width: 180,templet:Table.templet.time,search: 'range'},
                    {
                        width: 100, align: 'center', title: __('Operat'), init: Table.init, templet : Table.templet.operat, operat: ['delete']
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        add:function () {
            Controller.api.bindevent()
        },
        api: {
            bindevent: function () {
                Upload.api.bindEvent();
            }
        }
    };
    return Controller;
});