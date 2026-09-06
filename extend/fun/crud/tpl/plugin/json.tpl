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
    "entry": {
        "class": "plugins\\{%plugin%}\\Plugin",
        "file": "Plugin.php"
    },
    "load": {
        "services": "config/services.php",
        "events": "config/events.php",
        "routes": "routes/plugin.php"
    },
    "adminWeb": {
        "component": "entry.js",
        "files": ["entry.js"],
        "minFrontendVersion": "1.0.0",
        "permissions": [
            { "code": "{%plugin%}:dashboard:view", "name": "查看{%title%}" }
        ],
        "menu": [
            { "name": "{%title%}", "path": "/plugin/{%plugin%}/index", "permission": "{%plugin%}:dashboard:view" }
        ],
        "routes": [
            {
                "path": "/plugin/{%plugin%}/index",
                "name": "Plugin_{%plugin%}_Index",
                "component": "Index",
                "meta": {
                    "title": "{%title%}",
                    "permission": "{%plugin%}:dashboard:view"
                }
            }
        ]
    },
    "resources": {
        "public": { "source": "resources/public", "target": "plugin-assets/{%plugin%}/public" }
    },
    "migrations": { "path": "migrations" },
    "storage": { "path": "storage/{%plugin%}" },
    "purge": { "supported": false }
}