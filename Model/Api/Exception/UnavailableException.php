<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model\Api\Exception;

/**
 * TraceLogic is unreachable, timed out, rate limited or answered 5xx.
 */
class UnavailableException extends \RuntimeException
{
}
