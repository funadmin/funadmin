define([], function () {
    require.config(
        {
            shim: {
                // codemirror
                'codemirror':{
                    deps: [
                        'css!/static/addons/codemirror/plugins/codemirror/lib/codemirror.css',
                        'css!/static/addons/codemirror/plugins/codemirror/theme/monokai.css',
                        'css!/static/addons/codemirror/plugins/codemirror/theme/material.css',
                    ],
                    exports: 'codemirror',
                    init: function(codemirror) {
                        window.CodeMirror = codemirror;
                    }
                },
            },
        }
    );
});