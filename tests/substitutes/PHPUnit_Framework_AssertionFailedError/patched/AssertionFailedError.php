<?php declare(strict_types=1);
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Framework;

use Throwable;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 *
 * @phpstan-type PreProcessMessageCallback callable(string): string
 */
class AssertionFailedError extends Exception implements SelfDescribing
{
    /** @var ?PreProcessMessageCallback */
    public static mixed $preprocessMessage = null;

    public function __construct(mixed $message = '', mixed $code = 0, ?Throwable $previous = null)
    {
        if ((self::$preprocessMessage !== null) && is_string($message)) {
            $message = (self::$preprocessMessage)($message);
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
