<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * The server's preferences for model selection, requested of the client during sampling.
 *
 * Because LLMs can vary along multiple dimensions, choosing the "best" model is
 * rarely straightforward.  Different models excel in different areas—some are
 * faster but less capable, others are more capable but more expensive, and so
 * on. This interface allows servers to express their priorities across multiple
 * dimensions to help clients make an appropriate selection for their use case.
 *
 * These preferences are always advisory. The client MAY ignore them. It is also
 * up to the client to decide how to interpret these preferences and how to
 * balance them against other considerations.
 */
class ModelPreferences implements JsonSerializable
{
    /**
     * @param  ModelHint[]|null  $hints  Optional hints about the model to use.
     *
     * If multiple hints are specified, the client MUST evaluate them in order (such that the first match is taken).
     *
     * The client SHOULD prioritize these hints over the numeric priorities, but MAY still use the priorities to select from ambiguous matches.
     *
     * @param  float|null  $costPriority  How much to prioritize cost when selecting a model. A value of 0 means cost is not important, while
     * a value of 1 means cost is the most important factor. Minimum value is 0, maximum value is 1.
     *
     * @param  float|null  $speedPriority   How much to prioritize sampling speed (latency) when selecting a model. A value of 0 means
     * speed is not important, while a value of 1 means speed is the most important factor. Minimum value is 0, maximum value is 1.
     *
     * @param  float|null  $intelligencePriority   How much to prioritize intelligence and capabilities when selecting a  model. A value of 0
     *  means intelligence is not important, while a value of 1  means intelligence is the most important factor.
     */
    public function __construct(
        public readonly ?array $hints = null,
        public readonly ?float $costPriority = null,
        public readonly ?float $speedPriority = null,
        public readonly ?float $intelligencePriority = null,
    ) {
    }

    /**
     * @param ModelHint[]|null $hints Optional hints about the model to use.
     * @param float|null $costPriority How much to prioritize cost when selecting a model.
     * @param float|null $speedPriority How much to prioritize sampling speed (latency) when selecting a model.
     * @param float|null $intelligencePriority How much to prioritize intelligence and capabilities when selecting a model.
     */
    public static function make(?array $hints = null, ?float $costPriority = null, ?float $speedPriority = null, ?float $intelligencePriority = null): static
    {
        return new static($hints, $costPriority, $speedPriority, $intelligencePriority);
    }

    public function toArray(): array
    {
        $result = [];
        if ($this->hints !== null) {
            $result['hints'] = array_map(fn ($hint) => $hint->toArray(), $this->hints);
        }
        if ($this->costPriority !== null) {
            $result['costPriority'] = $this->costPriority;
        }
        if ($this->speedPriority !== null) {
            $result['speedPriority'] = $this->speedPriority;
        }
        if ($this->intelligencePriority !== null) {
            $result['intelligencePriority'] = $this->intelligencePriority;
        }
        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
