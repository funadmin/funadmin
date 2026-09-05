{
    "schema_version": 1,
    "name": "{%plugin%}",
    "title": "{%title%}",
    "description": "{%description%}",
    "author": "{%author%}",
    "version": "{%version%}",
    "requires": {
        "php": ">=8.1",
        "funadmin": "{%funadmin_version%}",
        "plugins": {}
    },
    "load": {
        "services": "config/services.php",
        "events": "config/events.php",
        "routes": "routes/plugin.php"
    },
    "admin_web": {
        "entry": "resources/admin/entry.js",
        "routes": [
            {
                "path": "/plugin/{%plugin%}/index",
                "name": "Plugin_{%plugin%}_Index",
                "component": "Index",
                "meta": {
                    "title": "{%title%}"
                }
            }
        ]
    }
}
