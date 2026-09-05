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
        "class": "plugins\\{%plugin%}\\Plugin"
    },
    "services": "config/services.php",
    "events": "config/events.php",
    "routes": "routes/plugin.php",
    "permissions": [],
    "menus": [],
    "admin_web": {
        "entry": "entry.js",
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
    },
    "resources": {
        "public": { "source": "resources/public", "target": "plugin-assets/{%plugin%}/public" },
        "admin": { "source": "resources/admin", "target": "plugin-assets/{%plugin%}" }
    },
    "migrations": { "path": "migrations" },
    "storage": { "path": "storage/{%plugin%}" },
    "purge": { "supported": false }
}
