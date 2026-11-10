<?php

namespace app\common\service\database;

/** SqlLexer 产出的词法单元；text 为源文原样（含引号与注释标记），offset 为在被分词文本中的字节偏移。 */
final class SqlToken
{
    public const CODE = 'code';
    public const SINGLE_QUOTED = 'single_quoted';
    public const DOUBLE_QUOTED = 'double_quoted';
    public const BACKTICK = 'backtick';
    public const BLOCK_COMMENT = 'block_comment';
    /** MySQL 可执行注释 /*!NNNNN … *\/：children 为注释体按代码分词的结果，version 为版本号（可带 M 前缀）。 */
    public const EXECUTABLE_COMMENT = 'executable_comment';
    public const DASH_COMMENT = 'dash_comment';
    public const HASH_COMMENT = 'hash_comment';
    public const SEPARATOR = 'separator';

    /** @param list<SqlToken> $children */
    public function __construct(
        public readonly string $type,
        public readonly string $text,
        public readonly int $offset,
        public readonly array $children = [],
        public readonly string $version = '',
    ) {
    }

    public function isComment(): bool
    {
        return $this->type === self::BLOCK_COMMENT || $this->type === self::DASH_COMMENT || $this->type === self::HASH_COMMENT;
    }
}
