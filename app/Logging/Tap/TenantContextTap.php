<?php

namespace App\Logging\Tap;

use App\Logging\TenantContextProcessor;
use Illuminate\Log\Logger;

class TenantContextTap
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new TenantContextProcessor());
    }
}
