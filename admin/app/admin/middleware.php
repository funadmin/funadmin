<?php

/** Admin Webman middleware pipeline. */
return [
    // app 子类通过 locale Cookie 同步当前语言
    \app\middleware\LoadLangPackMiddleware::class,
];

