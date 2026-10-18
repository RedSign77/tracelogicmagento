<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model\Api\Exception;

/**
 * TraceLogic rejected the request itself (4xx: bad key, validation error).
 */
class RequestException extends \RuntimeException
{
}
