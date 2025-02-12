<?php

/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace PHPUnit\Framework;

use Throwable;

/**
 * @phpstan-type PreProcessMessageCallback callable(AssertionFailedError $exceptionBeingConstructed, string $baseMessage, non-negative-int $numberOfStackFramesToSkip): string
 */
class AssertionFailedError extends Exception implements SelfDescribing
{
    /** @var ?PreProcessMessageCallback */
    public static mixed $preprocessMessage = null;

    public function __construct(string $message = '', int|string $code = 0, ?Throwable $previous = null)
    {
        if (self::$preprocessMessage !== null) {
            $message = (self::$preprocessMessage)(/* exceptionBeingConstructed */ $this, /* baseMessage */ $message, /* numberOfStackFramesToSkip */ 1);
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * Wrapper for getMessage() which is declared as final.
     */
    public function toString(): string
    {
        return $this->getMessage();
    }
}
