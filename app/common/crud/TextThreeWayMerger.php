<?php

declare(strict_types=1);

namespace app\common\crud;

/**
 * 使用确定性 LCS 行差异实现纯 PHP diff3；不执行外部命令。
 */
final class TextThreeWayMerger
{
    /** @return array{status: string, content?: string, baseContent?: string, localContent?: string, remoteContent?: string} */
    public function merge(string $base, string $local, string $remote): array
    {
        if ($local === $remote) {
            return ['status' => 'auto-merged', 'content' => $local];
        }
        if ($local === $base) {
            return ['status' => 'auto-merged', 'content' => $remote];
        }
        if ($remote === $base) {
            return ['status' => 'auto-merged', 'content' => $local];
        }

        $baseLines = $this->lines($base);
        $localChanges = $this->changes($baseLines, $this->lines($local));
        $remoteChanges = $this->changes($baseLines, $this->lines($remote));
        $changes = $localChanges;
        foreach ($remoteChanges as $remoteChange) {
            $duplicate = false;
            foreach ($localChanges as $localChange) {
                if ($this->sameChange($localChange, $remoteChange)) {
                    $duplicate = true;
                    break;
                }
                if ($this->overlaps($localChange, $remoteChange)) {
                    return [
                        'status' => 'conflict',
                        'baseContent' => $base,
                        'localContent' => $local,
                        'remoteContent' => $remote,
                    ];
                }
            }
            if (!$duplicate) {
                $changes[] = $remoteChange;
            }
        }

        usort($changes, static fn (array $left, array $right): int => [$right['start'], $right['end']] <=> [$left['start'], $left['end']]);
        $merged = $baseLines;
        foreach ($changes as $change) {
            array_splice($merged, $change['start'], $change['end'] - $change['start'], $change['lines']);
        }
        return ['status' => 'auto-merged', 'content' => implode('', $merged)];
    }

    /** @return list<string> */
    private function lines(string $content): array
    {
        if ($content === '') {
            return [];
        }
        $lines = preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($lines) ? $lines : [$content];
    }

    /** @return list<array{start: int, end: int, lines: list<string>}> */
    private function changes(array $base, array $variant): array
    {
        $baseCount = count($base);
        $variantCount = count($variant);
        $lcs = array_fill(0, $baseCount + 1, array_fill(0, $variantCount + 1, 0));
        for ($baseIndex = $baseCount - 1; $baseIndex >= 0; $baseIndex--) {
            for ($variantIndex = $variantCount - 1; $variantIndex >= 0; $variantIndex--) {
                $lcs[$baseIndex][$variantIndex] = $base[$baseIndex] === $variant[$variantIndex]
                    ? 1 + $lcs[$baseIndex + 1][$variantIndex + 1]
                    : max($lcs[$baseIndex + 1][$variantIndex], $lcs[$baseIndex][$variantIndex + 1]);
            }
        }

        $matches = [];
        $baseIndex = 0;
        $variantIndex = 0;
        while ($baseIndex < $baseCount && $variantIndex < $variantCount) {
            if ($base[$baseIndex] === $variant[$variantIndex]) {
                $matches[] = [$baseIndex++, $variantIndex++];
            } elseif ($lcs[$baseIndex + 1][$variantIndex] >= $lcs[$baseIndex][$variantIndex + 1]) {
                $baseIndex++;
            } else {
                $variantIndex++;
            }
        }
        $matches[] = [$baseCount, $variantCount];

        $changes = [];
        $previousBase = -1;
        $previousVariant = -1;
        foreach ($matches as [$matchedBase, $matchedVariant]) {
            $start = $previousBase + 1;
            $end = $matchedBase;
            $replacement = array_slice($variant, $previousVariant + 1, $matchedVariant - $previousVariant - 1);
            if ($start !== $end || $replacement !== []) {
                $changes[] = ['start' => $start, 'end' => $end, 'lines' => $replacement];
            }
            $previousBase = $matchedBase;
            $previousVariant = $matchedVariant;
        }
        return $changes;
    }

    private function sameChange(array $left, array $right): bool
    {
        return $left['start'] === $right['start'] && $left['end'] === $right['end'] && $left['lines'] === $right['lines'];
    }

    private function overlaps(array $left, array $right): bool
    {
        $leftInsert = $left['start'] === $left['end'];
        $rightInsert = $right['start'] === $right['end'];
        if ($leftInsert && $rightInsert) {
            return $left['start'] === $right['start'];
        }
        if ($leftInsert) {
            return $left['start'] >= $right['start'] && $left['start'] < $right['end'];
        }
        if ($rightInsert) {
            return $right['start'] >= $left['start'] && $right['start'] < $left['end'];
        }
        return max($left['start'], $right['start']) < min($left['end'], $right['end']);
    }
}
