<?php
declare(strict_types=1);

namespace Nutronyx\SDK\Contracts;

use Nutronyx\SDK\ExtensionRegistry;
use Nutronyx\SDK\EventBus;

interface AdminModuleInterface
{
    public function getIdentifier(): string;
    public function getName(): string;
    public function getVersion(): string;
    public function boot(ExtensionRegistry $registry, EventBus $eventBus): void;
}
