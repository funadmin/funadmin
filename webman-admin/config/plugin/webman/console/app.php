<?php
return [
    'enable' => true,

    'build_dir'  => BASE_PATH . DIRECTORY_SEPARATOR . 'build',

    'phar_filename' => 'webman.phar',

    'phar_format' => Phar::PHAR, // Phar archive format: Phar::PHAR, Phar::TAR, Phar::ZIP

    'phar_compression' => Phar::NONE, // Compression method for Phar archive: Phar::NONE, Phar::GZ, Phar::BZ2

    'bin_filename' => 'webman.bin',

    'signature_algorithm'=> Phar::SHA256, //set the signature algorithm for a phar and apply it. The signature algorithm must be one of Phar::MD5, Phar::SHA1, Phar::SHA256, Phar::SHA512, or Phar::OPENSSL.

    'private_key_file'  => '', // The file path for certificate or OpenSSL private key file.

    'exclude_pattern'   => '#^(?!.*(composer.json|/.github/|/.idea/|/.git/|/.setting/|/runtime/|/vendor-bin/|/build/|/vendor/webman/admin/))(.*)$#',

    'exclude_files'     => [
        '.env', 'LICENSE', 'composer.json', 'composer.lock', 'start.php', 'webman.phar', 'webman.bin'
    ],

    // php.ini entries embedded into the bin file. Swow's async file IO interleaves phar reads across coroutines and can only be disabled at startup.
    'custom_ini' => '
memory_limit = 256M
swow.async_file = 0
swow.async_tty = 0
    ',
];
