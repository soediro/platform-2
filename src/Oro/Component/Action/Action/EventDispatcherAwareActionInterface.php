<?php

namespace Oro\Component\Action\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

interface EventDispatcherAwareActionInterface
{
    /**
     * Add event dispatcher to the action
     */
    public function setDispatcher(EventDispatcherInterface $eventDispatcher);
}
